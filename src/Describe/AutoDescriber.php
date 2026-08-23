<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Describe;

use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Contracts\UserDescriber;
use HeiHallo\McpKit\Principal;

/**
 * Picks a describer by inspecting the user class: spatie roles, a model
 * with its own permissions, or the generic reader.
 */
class AutoDescriber implements UserDescriber
{
    public function __construct(protected PermissionChecker $permissions) {}

    public function describe(Principal $principal): UserDescription
    {
        return $this->describerFor($principal)->describe($principal);
    }

    protected function describerFor(Principal $principal): UserDescriber
    {
        $user = $principal->tokenable;

        if ($principal->isService()) {
            return new GenericDescriber($this->permissions);
        }

        if (method_exists($user, 'getRoleNames')) {
            return new SpatieRolesDescriber($this->permissions);
        }

        if (method_exists($user, 'hasAnyPermission') || method_exists($user, 'hasPermission')) {
            return new PermissionsTraitDescriber($this->permissions);
        }

        return new GenericDescriber($this->permissions);
    }
}
