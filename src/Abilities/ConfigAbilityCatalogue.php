<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Abilities;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Contracts\Config\Repository;

/**
 * The canonical list of token abilities (scopes), read from
 * mcp-kit.catalogue and mcp-kit.servers.
 *
 * Every ability maps to the permission its owner must still hold. A token
 * never out-ranks its human: abilities say what the token may do, the
 * permission says whether the person still may. Apps that prefer PHP
 * constants extend this class and override entries().
 */
class ConfigAbilityCatalogue implements AbilityCatalogue
{
    /** @var array<string, array{0: string, 1: ?string, 2: ?string}>|null */
    private ?array $entries = null;

    public function __construct(
        protected Repository $config,
        protected ServerRegistry $servers,
    ) {}

    /**
     * ability => [description, permission|'a|b'|null, server]. Accepts the
     * positional shape and the associative one (description/permission/server).
     *
     * @return array<string, array{0: string, 1: ?string, 2: ?string}>
     */
    protected function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $entries = [];

        foreach ((array) $this->config->get('mcp-kit.catalogue.abilities', []) as $ability => $entry) {
            if (is_string($entry)) {
                $entries[$ability] = [$entry, null, null];

                continue;
            }

            $entry = (array) $entry;

            $entries[$ability] = [
                (string) ($entry['description'] ?? $entry[0] ?? ''),
                isset($entry['permission']) ? (string) $entry['permission'] : (isset($entry[1]) ? (string) $entry[1] : null),
                isset($entry['server']) ? (string) $entry['server'] : (isset($entry[2]) ? (string) $entry[2] : null),
            ];
        }

        return $this->entries = $entries;
    }

    /**
     * @return list<string>
     */
    protected function explicitOnlyNames(): array
    {
        return array_values(array_map('strval', (array) $this->config->get('mcp-kit.catalogue.explicit_only', [])));
    }

    /**
     * @return array<string, string>
     */
    protected function aliases(): array
    {
        return (array) $this->config->get('mcp-kit.catalogue.aliases', []);
    }

    protected function superWildcard(): ?string
    {
        $wildcard = $this->config->get('mcp-kit.catalogue.super_wildcard');

        return is_string($wildcard) && $wildcard !== '' ? $wildcard : null;
    }

    /**
     * Ordinary (non-explicit-only) catalogue entries.
     *
     * @return array<string, array{0: string, 1: ?string, 2: ?string}>
     */
    protected function ordinary(): array
    {
        $explicit = $this->explicitOnlyNames();

        return array_filter($this->entries(), fn (string $ability): bool => ! in_array($ability, $explicit, true), ARRAY_FILTER_USE_KEY);
    }

    public function all(): array
    {
        $all = array_map(static fn (array $entry): string => $entry[0], $this->ordinary());
        $explicit = $this->explicitOnlyNames();

        foreach ($this->serverWildcards() as $wildcard) {
            $servers = $this->serversGrantedBy($wildcard);
            $prefix = rtrim($wildcard, '*');
            $note = $explicit === [] ? '' : '. Explicit-only abilities are never included and have to be named on the token';

            $all[$wildcard] = sprintf(
                'Every %s ability (%s)%s',
                $prefix,
                implode(', ', array_map(fn (string $key): string => $this->servers->get($key)?->label ?? $key, $servers)),
                $note,
            );
        }

        if (($super = $this->superWildcard()) !== null) {
            $all[$super] = 'Every ability on every server'.($explicit === [] ? '' : ' except the explicit-only ones');
        }

        return $all;
    }

    public function explicitOnly(): array
    {
        $entries = $this->entries();
        $explicit = [];

        foreach ($this->explicitOnlyNames() as $ability) {
            $explicit[$ability] = $entries[$ability][0] ?? $ability;
        }

        return $explicit;
    }

    public function names(): array
    {
        return array_values(array_unique([...array_keys($this->all()), ...$this->explicitOnlyNames()]));
    }

    public function readOnly(): array
    {
        return array_values(array_filter(
            array_keys($this->ordinary()),
            fn (string $ability): bool => ! $this->isWrite($ability),
        ));
    }

    public function canonical(string $ability): string
    {
        return $this->aliases()[$ability] ?? $ability;
    }

    public function isLegacy(string $ability): bool
    {
        return array_key_exists($ability, $this->aliases());
    }

    public function isExplicitOnly(string $ability): bool
    {
        return in_array($this->canonical($ability), $this->explicitOnlyNames(), true);
    }

    public function serverWildcards(): array
    {
        $wildcards = [];

        foreach ($this->servers->all() as $definition) {
            if ($definition->wildcard !== null) {
                $wildcards[] = $definition->wildcard;
            }
        }

        return array_values(array_unique($wildcards));
    }

    public function isWildcard(string $ability): bool
    {
        $ability = $this->canonical($ability);

        return $ability === '*'
            || $ability === $this->superWildcard()
            || in_array($ability, $this->serverWildcards(), true)
            || $this->isFamilyWildcard($ability);
    }

    public function isFamilyWildcard(string $ability): bool
    {
        return $this->familyPrefix($this->canonical($ability)) !== null;
    }

    /**
     * The catalogue prefix a family wildcard covers ("staff:imports:"), or null.
     */
    protected function familyPrefix(string $ability): ?string
    {
        if (! str_ends_with($ability, ':*')) {
            return null;
        }

        $prefix = substr($ability, 0, -1);

        foreach (array_keys($this->entries()) as $known) {
            if (str_starts_with($known, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    public function exists(string $ability): bool
    {
        $ability = $this->canonical($ability);

        return array_key_exists($ability, $this->entries())
            || $ability === $this->superWildcard()
            || in_array($ability, $this->serverWildcards(), true)
            || $this->isFamilyWildcard($ability);
    }

    public function isWrite(string $ability): bool
    {
        $ability = $this->canonical($ability);

        if ($this->isWildcard($ability)) {
            return true;
        }

        if (in_array($ability, (array) $this->config->get('mcp-kit.catalogue.read_abilities', []), true)) {
            return false;
        }

        return ! str_ends_with($ability, ':read');
    }

    public function wildcardsFor(string $ability): array
    {
        $ability = $this->canonical($ability);

        if ($this->isExplicitOnly($ability)) {
            return [];
        }

        $wildcards = [];
        $segments = explode(':', $ability);

        while (count($segments) > 1) {
            array_pop($segments);
            $wildcards[] = implode(':', $segments).':*';
        }

        $server = $this->serverFor($ability);

        if ($server !== null && ($serverWildcard = $this->servers->get($server)?->wildcard) !== null) {
            $wildcards[] = $serverWildcard;
        }

        if (($super = $this->superWildcard()) !== null) {
            $wildcards[] = $super;
        }

        $wildcards[] = '*';

        return array_values(array_unique($wildcards));
    }

    public function expand(array $tokenAbilities): array
    {
        $held = array_values(array_filter($tokenAbilities, 'is_string'));
        $canonicalHeld = array_map(fn (string $ability): string => $this->canonical($ability), $held);
        $granted = [];

        foreach (array_keys($this->entries()) as $ability) {
            if (in_array($ability, $canonicalHeld, true)) {
                $granted[] = $ability;

                continue;
            }

            if (array_intersect($this->wildcardsFor($ability), $canonicalHeld) !== []) {
                $granted[] = $ability;
            }
        }

        return $granted;
    }

    public function requiredPermissions(string $ability): array
    {
        $permission = $this->entries()[$this->canonical($ability)][1] ?? null;

        return $permission === null || $permission === '' ? [] : explode('|', $permission);
    }

    public function allowedForServiceClient(string $ability): bool
    {
        $ability = $this->canonical($ability);

        return ! $this->isWrite($ability)
            || in_array($ability, (array) $this->config->get('mcp-kit.catalogue.service_client_writes', []), true);
    }

    public function serverFor(string $ability): ?string
    {
        $ability = $this->canonical($ability);
        $entry = $this->entries()[$ability] ?? null;

        if ($entry !== null) {
            return $entry[2] ?? $this->servers->firstKey();
        }

        foreach ($this->servers->all() as $key => $definition) {
            if ($definition->wildcard === $ability) {
                return $key;
            }
        }

        $prefix = $this->familyPrefix($ability);

        if ($prefix !== null) {
            foreach ($this->entries() as $known => $entry) {
                if (str_starts_with($known, $prefix)) {
                    return $entry[2] ?? $this->servers->firstKey();
                }
            }
        }

        return null;
    }

    public function serversFor(array $abilities): array
    {
        $reached = [];
        $order = array_keys($this->servers->canonical());

        foreach ($abilities as $ability) {
            if (! is_string($ability)) {
                continue;
            }

            $ability = $this->canonical($ability);

            if ($ability === '*' || $ability === $this->superWildcard()) {
                return $order;
            }

            if ($this->isWildcard($ability)) {
                array_push($reached, ...$this->serversGrantedBy($ability));

                continue;
            }

            $server = $this->serverFor($ability);

            if ($server !== null) {
                $reached[] = $server;
            }
        }

        return array_values(array_intersect($order, array_unique($reached)));
    }

    /**
     * Every server a wildcard reaches: the servers of the abilities it
     * grants. Two servers sharing one wildcard fall out of this.
     *
     * @return list<string>
     */
    protected function serversGrantedBy(string $wildcard): array
    {
        $servers = [];

        foreach ($this->ordinary() as $ability => $entry) {
            if (in_array($wildcard, $this->wildcardsFor($ability), true)) {
                $servers[] = $entry[2] ?? $this->servers->firstKey();
            }
        }

        foreach ($this->servers->canonical() as $key => $definition) {
            if ($definition->wildcard === $wildcard) {
                $servers[] = $key;
            }
        }

        return array_values(array_intersect(array_keys($this->servers->canonical()), array_unique(array_filter($servers))));
    }

    public function referencedPermissions(): array
    {
        $permissions = [];

        foreach ($this->entries() as $entry) {
            if ($entry[1] !== null && $entry[1] !== '') {
                array_push($permissions, ...explode('|', $entry[1]));
            }
        }

        return array_values(array_unique($permissions));
    }

    public function description(string $ability): ?string
    {
        $ability = $this->canonical($ability);

        return $this->entries()[$ability][0] ?? $this->all()[$ability] ?? null;
    }

    public function prefixes(): array
    {
        $prefixes = [];

        foreach ([...array_keys($this->entries()), ...$this->serverWildcards(), ...array_keys($this->aliases())] as $ability) {
            $prefixes[] = explode(':', $ability)[0];
        }

        if (($super = $this->superWildcard()) !== null) {
            $prefixes[] = explode(':', $super)[0];
        }

        $prefixes = array_values(array_unique(array_filter($prefixes)));
        sort($prefixes);

        return $prefixes;
    }
}
