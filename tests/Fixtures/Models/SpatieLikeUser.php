<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Models;

use Illuminate\Support\Collection;

/**
 * The surface spatie/laravel-permission adds to a user, without its tables.
 */
class SpatieLikeUser extends User
{
    protected $table = 'users';

    /** @var list<string> */
    public array $roles = [];

    public function hasAnyPermission(array|string ...$permissions): bool
    {
        $wanted = is_array($permissions[0] ?? null) ? $permissions[0] : $permissions;

        return array_intersect($wanted, (array) $this->permissions) !== [];
    }

    public function hasAnyRole(array|string ...$roles): bool
    {
        $wanted = is_array($roles[0] ?? null) ? $roles[0] : $roles;

        return array_intersect($wanted, $this->roles) !== [];
    }

    public function getRoleNames(): Collection
    {
        return collect($this->roles);
    }

    public function getAllPermissions(): Collection
    {
        return collect((array) $this->permissions)->map(fn (string $name): object => (object) ['name' => $name]);
    }
}
