<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Presets;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;

/**
 * Ability presets offered when a person mints a token, read from
 * mcp-kit.token_presets. Presets are filtered by the person's permissions:
 * "work" for someone who only holds `chat` yields the chat abilities and
 * nothing else.
 */
class ConfigPresetResolver implements PresetResolver
{
    public function __construct(
        protected Repository $config,
        protected AbilityCatalogue $catalogue,
        protected PermissionChecker $permissions,
        protected PrincipalResolver $principals,
        protected ServerRegistry $servers,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function definitions(): array
    {
        return (array) $this->config->get('mcp-kit.token_presets', []);
    }

    public function all(): array
    {
        $all = [];

        foreach ($this->definitions() as $key => $preset) {
            $all[$key] = [
                'label' => (string) ($preset['label'] ?? ucfirst((string) $key)),
                'description' => (string) ($preset['description'] ?? ''),
            ];
        }

        return $all;
    }

    public function grantableFor(Authenticatable $user): array
    {
        $principal = $this->principals->resolve($user);

        if ($principal === null || $principal->blocked) {
            return [];
        }

        // Presets are for staff: a customer with a login gets none when
        // mcp-kit.tokens.staff_only is on.
        if ($this->config->get('mcp-kit.tokens.staff_only', false) && ! $principal->staff) {
            return [];
        }

        return array_values(array_filter(
            array_keys($this->catalogue->all()),
            function (string $ability) use ($user): bool {
                if ($this->catalogue->isWildcard($ability)) {
                    return false;
                }

                // Abilities of a server that opted out of presets (a
                // customer-facing server) are never offered on the staff page.
                $server = $this->catalogue->serverFor($ability);

                if ($server !== null && ! ($this->servers->get($server)?->presets ?? true)) {
                    return false;
                }

                $permissions = $this->catalogue->requiredPermissions($ability);

                return $permissions === [] || $this->permissions->hasAnyPermission($user, $permissions);
            },
        ));
    }

    public function abilitiesFor(Authenticatable $user, string $preset): array
    {
        $definition = $this->definitions()[$preset] ?? null;

        if ($definition === null) {
            return [];
        }

        $principal = $this->principals->resolve($user);

        if ($principal === null || $principal->blocked) {
            return [];
        }

        if (($definition['privileged'] ?? false) && ! $principal->privileged) {
            return [];
        }

        $grantable = $this->grantableFor($user);
        $grant = $definition['grant'] ?? 'grantable';

        if (is_array($grant)) {
            return array_values(array_intersect($grant, $grantable));
        }

        return match ($grant) {
            'reads' => array_values(array_filter($grantable, fn (string $ability): bool => ! $this->catalogue->isWrite($ability))),
            'grantable' => $grantable,
            'wildcards' => $this->wildcardsFor($user),
            default => [],
        };
    }

    /**
     * The server wildcards (or the super wildcard) — only for privileged,
     * unblocked owners, and only over servers the person may reach.
     *
     * @return list<string>
     */
    protected function wildcardsFor(Authenticatable $user): array
    {
        $principal = $this->principals->resolve($user);

        if ($principal === null || ! $principal->privileged || $principal->blocked) {
            return [];
        }

        $wildcards = [];

        foreach ($this->servers->all() as $definition) {
            if ($definition->presets && $definition->wildcard !== null) {
                $wildcards[] = $definition->wildcard;
            }
        }

        $wildcards = array_values(array_unique($wildcards));

        if ($wildcards === [] && ($super = $this->config->get('mcp-kit.catalogue.super_wildcard')) !== null) {
            return [(string) $super];
        }

        return $wildcards;
    }

    public function availableFor(Authenticatable $user): array
    {
        $available = [];

        foreach ($this->all() as $key => $preset) {
            $abilities = $this->abilitiesFor($user, $key);

            if ($abilities !== []) {
                $available[$key] = [...$preset, 'abilities' => $abilities];
            }
        }

        return $available;
    }

    public function extrasFor(Authenticatable $user): array
    {
        $principal = $this->principals->resolve($user);

        if ($principal === null || ! $principal->privileged || $principal->blocked) {
            return [];
        }

        return array_filter(
            $this->catalogue->explicitOnly(),
            function (string $description, string $ability) use ($user): bool {
                $permissions = $this->catalogue->requiredPermissions($ability);

                return $permissions === [] || $this->permissions->hasAnyPermission($user, $permissions);
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function labelForAbilities(array $abilities): string
    {
        $abilities = array_values(array_filter($abilities, 'is_string'));
        $explicit = array_keys($this->catalogue->explicitOnly());
        $extras = array_values(array_intersect($abilities, $explicit));
        $base = array_values(array_diff($abilities, $explicit));

        $label = match (true) {
            $base === [] => 'None',
            $this->holdsWildcard($base) => $this->labelOfGrant('wildcards') ?? 'Full',
            $this->matchesListPreset($base) !== null => $this->matchesListPreset($base),
            array_filter($base, fn (string $ability): bool => $this->catalogue->isWrite($ability)) === [] => $this->labelOfGrant('reads') ?? 'Read only',
            default => $this->labelOfGrant('grantable') ?? 'Work',
        };

        if ($extras !== []) {
            $label .= ' + '.implode(', ', array_map(
                static fn (string $ability): string => substr($ability, (int) strpos($ability, ':') + 1),
                $extras,
            ));
        }

        return $label;
    }

    /**
     * The label of the first preset with this grant kind, so labels follow
     * the app's own preset names (read/support/admin as much as
     * read/work/full).
     */
    protected function labelOfGrant(string $grant): ?string
    {
        foreach ($this->definitions() as $key => $preset) {
            if (($preset['grant'] ?? 'grantable') === $grant) {
                return (string) ($preset['label'] ?? $key);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $base
     */
    protected function holdsWildcard(array $base): bool
    {
        foreach ($base as $ability) {
            $ability = $this->catalogue->canonical($ability);

            if ($ability === '*' || $ability === $this->config->get('mcp-kit.catalogue.super_wildcard') || in_array($ability, $this->catalogue->serverWildcards(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A preset defined as an explicit list ("analyst" => ['reports:read'])
     * labels tokens that hold exactly that list, or its wildcard.
     *
     * @param  list<string>  $base
     */
    protected function matchesListPreset(array $base): ?string
    {
        sort($base);

        foreach ($this->definitions() as $key => $preset) {
            $grant = $preset['grant'] ?? null;

            if (! is_array($grant)) {
                continue;
            }

            $expected = array_values($grant);
            sort($expected);

            if ($base === $expected) {
                return (string) ($preset['label'] ?? $key);
            }
        }

        return null;
    }

    public function grantsWrite(array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if (is_string($ability) && ($ability === '*' || $this->catalogue->isWrite($ability))) {
                return true;
            }
        }

        return false;
    }

    public function grantsExplicitOnly(array $abilities): bool
    {
        return array_intersect(array_filter($abilities, 'is_string'), array_keys($this->catalogue->explicitOnly())) !== [];
    }

    public function expiresDaysFor(string $preset): ?int
    {
        $days = $this->definitions()[$preset]['expires_days'] ?? null;

        return $days === null ? null : (int) $days;
    }
}
