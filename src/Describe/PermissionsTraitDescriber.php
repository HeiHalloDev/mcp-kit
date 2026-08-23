<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Describe;

use HeiHallo\McpKit\Principal;

/**
 * A users model with its own permission trait: a `permissions` attribute
 * (list of keys) and a `role` attribute. Known permissions come from the
 * checker; the attribute is the cheaper source when present.
 */
class PermissionsTraitDescriber extends GenericDescriber
{
    public function describe(Principal $principal): UserDescription
    {
        $user = $principal->tokenable;
        $base = parent::describe($principal);

        $own = method_exists($user, 'getAttribute') ? $user->getAttribute('permissions') : null;

        if (! is_array($own) || $own === []) {
            return $base;
        }

        $labels = (array) config('mcp-kit.describer_options.permission_labels', []);

        return $base->with([
            'permissions' => array_values(array_map(fn ($p): string => (string) ($labels[$p] ?? $p), $own)),
        ]);
    }
}
