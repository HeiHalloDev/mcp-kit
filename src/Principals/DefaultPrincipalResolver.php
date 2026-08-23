<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Principals;

use Closure;
use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\ServiceClient;
use HeiHallo\McpKit\Enums\PrincipalKind;
use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A tokenable of the configured service-client model is a Service (blocked
 * when inactive); any other Authenticatable is a Person (blocked when its
 * isBlocked() says so). Staff and privileged come from the PermissionChecker.
 */
class DefaultPrincipalResolver implements PrincipalResolver
{
    /** @var (Closure(Authenticatable): bool)|null */
    protected static ?Closure $blockedUsing = null;

    public function __construct(protected PermissionChecker $permissions) {}

    /**
     * Decide "blocked" differently: McpKit::blockedUsing(fn ($user) => $user->status !== 'active').
     *
     * @param  (Closure(Authenticatable): bool)|null  $callback
     */
    public static function blockedUsing(?Closure $callback): void
    {
        static::$blockedUsing = $callback;
    }

    public function resolve(Authenticatable $tokenable): ?Principal
    {
        $token = method_exists($tokenable, 'currentAccessToken') ? $tokenable->currentAccessToken() : null;
        $token = $token instanceof PersonalAccessToken ? $token : null;

        $serviceModel = config('mcp-kit.models.service_client');

        if ($tokenable instanceof ServiceClient || (is_string($serviceModel) && $tokenable instanceof $serviceModel)) {
            return new Principal(
                kind: PrincipalKind::Service,
                tokenable: $tokenable,
                name: $tokenable instanceof ServiceClient ? $tokenable->displayName() : (string) ($tokenable->name ?? 'service'),
                email: null,
                blocked: $tokenable instanceof ServiceClient ? ! $tokenable->isActive() : ! (bool) ($tokenable->is_active ?? true),
                staff: false,
                privileged: false,
                token: $token,
            );
        }

        return new Principal(
            kind: PrincipalKind::Person,
            tokenable: $tokenable,
            name: (string) ($tokenable->name ?? $tokenable->email ?? $tokenable->getAuthIdentifier()),
            email: isset($tokenable->email) ? (string) $tokenable->email : null,
            blocked: $this->isBlocked($tokenable),
            staff: $this->permissions->isStaff($tokenable),
            privileged: $this->permissions->isPrivileged($tokenable),
            token: $token,
        );
    }

    protected function isBlocked(Authenticatable $user): bool
    {
        if (static::$blockedUsing !== null) {
            return (bool) (static::$blockedUsing)($user);
        }

        if (method_exists($user, 'isBlocked')) {
            return (bool) $user->isBlocked();
        }

        if (method_exists($user, 'getAttribute')) {
            if ($user->getAttribute('blocked_at') !== null) {
                return true;
            }

            $active = $user->getAttribute('is_active');

            if ($active !== null) {
                return ! (bool) $active;
            }
        }

        return false;
    }
}
