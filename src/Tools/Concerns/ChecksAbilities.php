<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools\Concerns;

use HeiHallo\McpKit\Audit\McpCallContext;
use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Events\AbilityDenied;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Token abilities are the scopes. A server wildcard or family wildcard
 * grants every ordinary ability beneath it; explicit-only abilities must be
 * named on the token. On top of that the token's OWNER must still be staff
 * (where the server requires it) and hold the permission behind the
 * ability, re-checked on every call — a token never out-ranks its human.
 * A service client may only read, plus the writes the catalogue lists.
 */
trait ChecksAbilities
{
    protected function hasAbility(Request $request, string $ability): bool
    {
        return $this->abilityDenial($request, $ability) === null;
    }

    /**
     * The error response when the request may not use the ability, or null.
     */
    protected function requireAbility(Request $request, string $ability): ?Response
    {
        $denial = $this->abilityDenial($request, $ability);

        return $denial === null ? null : Response::error($denial);
    }

    /**
     * Why the request may not use the ability, or null when it may. Tools
     * return this straight to the agent so it knows which scope to ask for.
     */
    protected function abilityDenial(Request $request, string $ability): ?string
    {
        $denial = $this->computeAbilityDenial($request, $ability);

        if ($denial !== null) {
            $context = app(McpCallContext::class);

            if ($context->isActive()) {
                $context->denied($ability, $denial);
            }

            $tokenable = $request->user();
            $principal = $tokenable ? app(PrincipalResolver::class)->resolve($tokenable) : null;

            event(new AbilityDenied($principal, $ability, $denial, method_exists($this, 'name') ? $this->name() : null));
        }

        if ($denial !== null) {
            return $denial;
        }

        return $this->unknownParameterRefusal($request);
    }

    /**
     * An argument the tool does not declare is silently dropped by every
     * `$request->get()` below, and the tool then answers confidently about
     * something else. `get_available_slots` given `user_id` ignored it,
     * fell back to the caller, and reported that *they* had no booking
     * calendar — naming the wrong person for the wrong reason.
     *
     * A refusal that names the real parameter is worth more than an answer
     * to a question nobody asked. Service clients are exempt: their calls
     * are code we control and change deliberately, not a model guessing.
     */
    protected function unknownParameterRefusal(Request $request): ?string
    {
        if (! config('mcp-kit.strict_parameters', true)) {
            return null;
        }

        $tokenable = $request->user();
        $principal = $tokenable ? app(PrincipalResolver::class)->resolve($tokenable) : null;

        if ($principal === null || ! $principal->isPerson()) {
            return null;
        }

        $declared = $this->declaredParameters();

        // No declared schema means nothing to judge against.
        if ($declared === []) {
            return null;
        }

        $allowed = [...$declared, ...(array) config('mcp-kit.always_allowed_parameters', ['confirm'])];
        $unknown = array_values(array_diff(array_keys($request->all()), $allowed));

        if ($unknown === []) {
            return null;
        }

        $lines = [];

        foreach ($unknown as $parameter) {
            $closest = $this->closestParameter((string) $parameter, $declared);

            $lines[] = $closest === null
                ? sprintf('`%s` is not a parameter here.', $parameter)
                : sprintf('`%s` is not a parameter here — did you mean `%s`?', $parameter, $closest);
        }

        return implode(' ', $lines).' Nothing was done, because a dropped argument produces a confident answer to a different question. Parameters: '.implode(', ', $declared).'.';
    }

    /**
     * @return list<string>
     */
    protected function declaredParameters(): array
    {
        $schema = method_exists($this, 'resolveInputSchema')
            ? $this->resolveInputSchema()
            : (property_exists($this, 'inputSchema') ? $this->inputSchema : []);

        $properties = $schema['properties'] ?? [];

        return is_array($properties) ? array_map(strval(...), array_keys($properties)) : [];
    }

    /**
     * @param  list<string>  $declared
     */
    protected function closestParameter(string $parameter, array $declared): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($declared as $candidate) {
            $distance = levenshtein(strtolower($parameter), strtolower($candidate));

            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        // Far enough apart that a suggestion would be a guess, not a hint.
        return $bestDistance <= max(3, (int) floor(strlen($parameter) / 2)) ? $best : null;
    }

    private function computeAbilityDenial(Request $request, string $ability): ?string
    {
        $tokenable = $request->user();

        if ($tokenable === null) {
            return 'Authentication required.';
        }

        $catalogue = app(AbilityCatalogue::class);
        $principal = app(PrincipalResolver::class)->resolve($tokenable);

        if ($principal === null) {
            return 'This token is not permitted to use the MCP server.';
        }

        $ability = $catalogue->canonical($ability);
        $explicitOnly = $catalogue->isExplicitOnly($ability);

        if (! $this->tokenGrants($principal, $ability)) {
            if ($explicitOnly) {
                $server = $catalogue->serverFor($ability);
                $wildcard = $server !== null ? app(ServerRegistry::class)->get($server)?->wildcard : null;
                $hint = $wildcard !== null ? " Note that {$wildcard} does not grant this — it has to be named on the token." : ' It has to be named on the token.';

                return "Insufficient permissions. Required ability: {$ability}.{$hint}";
            }

            return "Insufficient permissions. Required ability: {$ability}";
        }

        if ($principal->isService()) {
            if ($principal->blocked) {
                return 'This service client is disabled.';
            }

            return $catalogue->allowedForServiceClient($ability)
                ? null
                : "This token belongs to a service client and {$ability} changes data. Writes need a personal token minted by a staff member so the change is attributable.";
        }

        if ($principal->blocked) {
            return 'Your account is blocked.';
        }

        $server = $catalogue->serverFor($ability);
        $definition = $server !== null ? app(ServerRegistry::class)->get($server) : null;

        if ($definition?->requiresStaff && ! $principal->staff) {
            $staffPermission = config('mcp-kit.permission_rules.staff_permission');

            return is_string($staffPermission) && $staffPermission !== ''
                ? "Your account is no longer staff (missing the '{$staffPermission}' permission), so this token cannot be used for staff tools."
                : 'Your account is no longer staff, so this token cannot be used for staff tools.';
        }

        if ($explicitOnly && ! $principal->privileged) {
            $label = (string) config('mcp-kit.permission_rules.privileged_label', 'a privileged role');

            return "The {$ability} ability requires {$label}.";
        }

        $permissions = $catalogue->requiredPermissions($ability);

        if ($permissions !== [] && ! app(PermissionChecker::class)->hasAnyPermission($principal->tokenable, $permissions)) {
            $label = implode("' or '", $permissions);
            $ask = (string) config('mcp-kit.permission_rules.ask_label', 'ask an administrator');

            return "Your account does not hold the '{$label}' permission — {$ask}.";
        }

        return null;
    }

    /**
     * The token names the ability, a legacy alias of it, a wildcard over it,
     * the super wildcard or '*' (explicit-only: the ability or alias only).
     */
    private function tokenGrants(Principal $principal, string $ability): bool
    {
        $catalogue = app(AbilityCatalogue::class);
        $candidates = [$ability, ...$catalogue->wildcardsFor($ability)];

        foreach ((array) config('mcp-kit.catalogue.aliases', []) as $legacy => $canonical) {
            if (in_array($canonical, $candidates, true)) {
                $candidates[] = (string) $legacy;
            }
        }

        $tokenable = $principal->tokenable;

        foreach (array_unique($candidates) as $candidate) {
            if (method_exists($tokenable, 'tokenCan') && $tokenable->tokenCan($candidate)) {
                return true;
            }
        }

        return false;
    }
}
