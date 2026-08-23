<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Permissions;

use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Permissions\Concerns\ReadsPermissionRules;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Permissions are Gate abilities: `$user->can('edit things')`. Works with
 * any Laravel app, including ones that define abilities in a provider.
 */
class GatePermissionChecker implements PermissionChecker
{
    use ReadsPermissionRules;

    public function __construct(protected Gate $gate) {}

    public function hasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        $gate = $this->gate->forUser($user);

        foreach ($permissions as $permission) {
            if ($gate->check($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isStaff(Authenticatable $user): bool
    {
        $permission = $this->rule('staff_permission');

        return ! is_string($permission) || $permission === '' || $this->gate->forUser($user)->check($permission);
    }

    public function isPrivileged(Authenticatable $user): bool
    {
        $permission = $this->rule('privileged_permission');

        if (is_string($permission) && $permission !== '' && $this->gate->forUser($user)->check($permission)) {
            return true;
        }

        return $this->userHasAnyRole($user, $this->privilegedRoles());
    }

    public function knownPermissions(): array
    {
        return $this->configuredKnownPermissions() ?? array_values(array_map('strval', array_keys($this->gate->abilities())));
    }
}
