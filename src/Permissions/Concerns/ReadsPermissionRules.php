<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Permissions\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;

trait ReadsPermissionRules
{
    protected function rule(string $key): mixed
    {
        return config("mcp-kit.permission_rules.{$key}");
    }

    /**
     * @return list<string>
     */
    protected function privilegedRoles(): array
    {
        return array_values(array_map('strval', (array) $this->rule('privileged_roles')));
    }

    /**
     * The configured list, or the keys of the configured config entry.
     *
     * @return list<string>|null
     */
    protected function configuredKnownPermissions(): ?array
    {
        $known = $this->rule('known');

        if (is_array($known)) {
            return array_values(array_map('strval', $known));
        }

        $from = $this->rule('known_from');

        if (is_string($from) && $from !== '') {
            $entries = config($from);

            return is_array($entries) ? array_values(array_map('strval', array_keys($entries))) : [];
        }

        return null;
    }

    /**
     * Roles via whatever the model offers: spatie's hasAnyRole(), a `role`
     * attribute (enum or string), or nothing.
     *
     * @param  list<string>  $roles
     */
    protected function userHasAnyRole(Authenticatable $user, array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return (bool) $user->hasAnyRole($roles);
        }

        $role = method_exists($user, 'getAttribute') ? $user->getAttribute('role') : null;

        if ($role instanceof \BackedEnum) {
            $role = $role->value;
        } elseif ($role instanceof \UnitEnum) {
            $role = $role->name;
        }

        if (! is_string($role) && ! is_int($role)) {
            return false;
        }

        foreach ($roles as $candidate) {
            if (strcasecmp((string) $role, $candidate) === 0) {
                return true;
            }
        }

        return false;
    }
}
