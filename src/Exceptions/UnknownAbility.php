<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Exceptions;

use InvalidArgumentException;

/**
 * A playbook named an ability the catalogue does not have. Carries the
 * abilities that map to it when it was a permission name instead, so the
 * caller can point the assistant at the right one.
 */
final class UnknownAbility extends InvalidArgumentException
{
    /**
     * @param  list<string>  $forPermission
     */
    public function __construct(public readonly string $ability, public readonly array $forPermission = [])
    {
        parent::__construct("There is no ability called '{$ability}'.");
    }
}
