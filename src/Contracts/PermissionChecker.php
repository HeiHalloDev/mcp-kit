<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface PermissionChecker
{
    /**
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(Authenticatable $user, array $permissions): bool;

    public function isStaff(Authenticatable $user): bool;

    public function isPrivileged(Authenticatable $user): bool;

    /**
     * Every permission name the app knows — the catalogue may only
     * reference these.
     *
     * @return list<string>
     */
    public function knownPermissions(): array;
}
