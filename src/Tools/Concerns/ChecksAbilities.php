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

        return $denial;
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
            return 'Your account is no longer staff, so this token cannot be used for staff tools.';
        }

        if ($explicitOnly && ! $principal->privileged) {
            $label = (string) config('mcp-kit.permission_rules.privileged_label', 'a privileged role');

            return "The {$ability} ability requires {$label}.";
        }

        $permissions = $catalogue->requiredPermissions($ability);

        if ($permissions !== [] && ! app(PermissionChecker::class)->hasAnyPermission($principal->tokenable, $permissions)) {
            $label = implode("' or '", $permissions);
            $ask = (string) config('mcp-kit.permission_rules.ask_label', 'ask an administrator');

            return "Your account no longer holds the '{$label}' permission — {$ask}.";
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
