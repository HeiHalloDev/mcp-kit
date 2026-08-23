<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tokens;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\TokenPolicy;
use HeiHallo\McpKit\Events\TokenMinted;
use HeiHallo\McpKit\Events\TokenRevoked;
use HeiHallo\McpKit\Exceptions\TokenRefused;
use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The one place personal tokens are created and revoked: the command, the
 * tokens page and any app code go through here, so the checks, the naming
 * and the audit trail never drift.
 */
class TokenMinter
{
    public function __construct(
        protected AbilityCatalogue $catalogue,
        protected PresetResolver $presets,
        protected PermissionChecker $permissions,
        protected PrincipalResolver $principals,
        protected TokenPolicy $policy,
        protected AuditWriter $audit,
    ) {}

    /**
     * @param  list<string>  $abilities
     *
     * @throws TokenRefused
     */
    public function mint(Authenticatable $owner, string $label, array $abilities, ?int $days = null, ?Principal $by = null, ?string $preset = null): NewAccessToken
    {
        $principal = $this->principals->resolve($owner);

        if ($principal === null || $principal->isService()) {
            throw new TokenRefused('Personal tokens belong to people. Use mcp:client-token for a service client.');
        }

        if ($principal->blocked) {
            throw new TokenRefused("{$principal->name} is blocked — unblock the user before minting a token.");
        }

        if (! method_exists($owner, 'createToken')) {
            throw new TokenRefused('The users model does not use Laravel\Sanctum\HasApiTokens.');
        }

        $abilities = $this->validate($principal, $abilities);

        if ($abilities === []) {
            throw new TokenRefused('No abilities to grant — check the user\'s role and permissions.');
        }

        $name = $this->policy->name($label);
        $token = $owner->createToken($name, $abilities, $this->policy->expiresAt($days));

        event(new TokenMinted($token->accessToken, $by ?? $principal));

        $this->audit->recordToken('minted', $token->accessToken, $by ?? $principal, array_filter([
            'preset' => $preset,
            'days' => $days ?? $this->policy->defaultDays(),
        ]));

        return $token;
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     *
     * @throws TokenRefused
     */
    public function validate(Principal $principal, array $abilities): array
    {
        $abilities = array_values(array_unique(array_filter(array_map('trim', $abilities))));
        $owner = $principal->tokenable;

        foreach ($abilities as $ability) {
            if ($this->catalogue->isLegacy($ability)) {
                throw new TokenRefused("'{$ability}' is a legacy alias — use '{$this->catalogue->canonical($ability)}'.");
            }

            if ($ability !== '*' && ! $this->catalogue->exists($ability)) {
                throw new TokenRefused("Unknown ability '{$ability}'. Known: ".implode(', ', $this->catalogue->names()).'.');
            }

            if (($ability === '*' || $this->catalogue->isExplicitOnly($ability) || $this->catalogue->isWildcard($ability)) && ! $principal->privileged) {
                $label = (string) config('mcp-kit.permission_rules.privileged_label', 'a privileged role');

                throw new TokenRefused("{$ability} can only be held by someone with {$label} — {$principal->name} is not.");
            }

            $permissions = $this->catalogue->requiredPermissions($ability);

            if ($permissions !== [] && ! $this->permissions->hasAnyPermission($owner, $permissions)) {
                throw new TokenRefused("{$principal->name} lacks the '".implode("' or '", $permissions)."' permission, so {$ability} would be refused on every call.");
            }
        }

        return $abilities;
    }

    /**
     * Every kit token of a person.
     *
     * @return int how many were revoked
     */
    public function revokeAll(Authenticatable $owner, ?Principal $by = null): int
    {
        if (! method_exists($owner, 'tokens')) {
            return 0;
        }

        $count = 0;

        foreach ($owner->tokens()->get() as $token) {
            if ($token instanceof PersonalAccessToken && $this->policy->isKitToken((string) $token->name)) {
                $this->revoke($token, $by);
                $count++;
            }
        }

        return $count;
    }

    public function revoke(PersonalAccessToken $token, ?Principal $by = null): void
    {
        $token->delete();

        event(new TokenRevoked($token, $by));

        $this->audit->recordToken('revoked', $token, $by);
    }
}
