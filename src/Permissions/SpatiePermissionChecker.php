<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Permissions;

use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Exceptions\MissingDependency;
use HeiHallo\McpKit\Permissions\Concerns\ReadsPermissionRules;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * spatie/laravel-permission: roles and permissions live in the database.
 */
class SpatiePermissionChecker implements PermissionChecker
{
    use ReadsPermissionRules;

    public function __construct()
    {
        if (! class_exists(PermissionRegistrar::class)) {
            throw MissingDependency::for('spatie/laravel-permission', 'mcp-kit.permissions = SpatiePermissionChecker');
        }
    }

    public function hasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }

        return method_exists($user, 'hasAnyPermission') && (bool) $user->hasAnyPermission($permissions);
    }

    public function isStaff(Authenticatable $user): bool
    {
        $permission = $this->rule('staff_permission');

        return ! is_string($permission) || $permission === '' || $this->hasAnyPermission($user, [$permission]);
    }

    public function isPrivileged(Authenticatable $user): bool
    {
        $permission = $this->rule('privileged_permission');

        if (is_string($permission) && $permission !== '' && $this->hasAnyPermission($user, [$permission])) {
            return true;
        }

        return $this->userHasAnyRole($user, $this->privilegedRoles());
    }

    public function knownPermissions(): array
    {
        if (($known = $this->configuredKnownPermissions()) !== null) {
            return $known;
        }

        $model = config('permission.models.permission', Permission::class);

        return array_values(array_map('strval', $model::query()->pluck('name')->all()));
    }
}
