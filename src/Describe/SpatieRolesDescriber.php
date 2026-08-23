<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Describe;

use HeiHallo\McpKit\Principal;

/**
 * spatie/laravel-permission: the role is the person's role names, the
 * permissions their direct and role permissions.
 */
class SpatieRolesDescriber extends GenericDescriber
{
    public function describe(Principal $principal): UserDescription
    {
        $user = $principal->tokenable;
        $base = parent::describe($principal);

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        $permissions = method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions()->pluck('name')->all()
            : $base->permissions;

        $labels = (array) config('mcp-kit.describer_options.permission_labels', []);

        return $base->with([
            'role' => $roles === [] ? $base->role : implode(', ', array_map('strval', $roles)),
            'permissions' => array_values(array_map(fn ($p): string => (string) ($labels[$p] ?? $p), $permissions)),
        ]);
    }
}
