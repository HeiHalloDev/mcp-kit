<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Permissions;

use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Permissions\Concerns\ReadsPermissionRules;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The users model answers itself: hasAnyPermission(), isStaff() and
 * isPrivileged() on the model (any that is missing falls back to the
 * permission_rules). Known permissions come from permission_rules.known or
 * known_from.
 */
class ModelPermissionChecker implements PermissionChecker
{
    use ReadsPermissionRules;

    public function hasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }

        if (method_exists($user, 'hasAnyPermission')) {
            return (bool) $user->hasAnyPermission($permissions);
        }

        if (method_exists($user, 'hasPermission')) {
            foreach ($permissions as $permission) {
                if ($user->hasPermission($permission)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    public function isStaff(Authenticatable $user): bool
    {
        if (method_exists($user, 'isStaff')) {
            return (bool) $user->isStaff();
        }

        $permission = $this->rule('staff_permission');

        return ! is_string($permission) || $permission === '' || $this->hasAnyPermission($user, [$permission]);
    }

    public function isPrivileged(Authenticatable $user): bool
    {
        if (method_exists($user, 'isPrivileged')) {
            return (bool) $user->isPrivileged();
        }

        $permission = $this->rule('privileged_permission');

        if (is_string($permission) && $permission !== '' && $this->hasAnyPermission($user, [$permission])) {
            return true;
        }

        return $this->userHasAnyRole($user, $this->privilegedRoles());
    }

    public function knownPermissions(): array
    {
        return $this->configuredKnownPermissions() ?? [];
    }
}
