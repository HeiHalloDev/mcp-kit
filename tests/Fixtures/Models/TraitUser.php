<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Models;

/**
 * A users model with its own permission methods, as apps with a hand-rolled
 * HasPermissions trait have.
 */
class TraitUser extends User
{
    protected $table = 'users';

    /**
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return array_intersect($permissions, (array) $this->permissions) !== [];
    }

    public function isPrivileged(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return in_array('staff', (array) $this->permissions, true);
    }
}
