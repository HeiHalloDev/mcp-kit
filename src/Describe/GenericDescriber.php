<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Describe;

use BackedEnum;
use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Contracts\UserDescriber;
use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

/**
 * Reads what any Eloquent user tends to have: a `role` attribute (string or
 * enum with label()), a `team` relation or attribute with a name, and the
 * known permissions the person holds.
 */
class GenericDescriber implements UserDescriber
{
    public function __construct(protected PermissionChecker $permissions) {}

    public function describe(Principal $principal): UserDescription
    {
        $user = $principal->tokenable;

        return new UserDescription(
            name: $principal->name,
            email: $principal->email,
            role: $this->role($user),
            privileged: $principal->privileged,
            team: $this->team($user),
            inboxes: [],
            permissions: $this->heldPermissions($user),
            facts: [],
        );
    }

    protected function role(Authenticatable $user): ?string
    {
        $role = $user instanceof Model ? $user->getAttribute('role') : ($user->role ?? null);

        return $this->labelOf($role);
    }

    protected function team(Authenticatable $user): ?string
    {
        if (! $user instanceof Model) {
            return null;
        }

        try {
            $team = method_exists($user, 'team') ? $user->team : $user->getAttribute('team');
        } catch (Throwable) {
            return null;
        }

        if ($team instanceof Model) {
            return $this->labelOf($team->getAttribute('name') ?? $team->getAttribute('title'));
        }

        return $this->labelOf($team);
    }

    /**
     * @return list<string>
     */
    protected function heldPermissions(Authenticatable $user): array
    {
        $labels = (array) config('mcp-kit.describer_options.permission_labels', []);
        $held = [];

        foreach (array_slice($this->permissions->knownPermissions(), 0, 200) as $permission) {
            try {
                if ($this->permissions->hasAnyPermission($user, [$permission])) {
                    $held[] = (string) ($labels[$permission] ?? $permission);
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $held;
    }

    protected function labelOf(mixed $value): ?string
    {
        if ($value instanceof UnitEnum) {
            if (method_exists($value, 'label')) {
                return (string) $value->label();
            }

            return $value instanceof BackedEnum ? (string) $value->value : $value->name;
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return null;
    }
}
