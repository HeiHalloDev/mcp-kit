<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Links;

use HeiHallo\McpKit\Contracts\Links;

/**
 * No admin pages known: tools omit the admin_url keys.
 */
final class NullLinks implements Links
{
    public function for(object $model): ?string
    {
        return null;
    }

    public function list(string $key, array $filters = []): ?string
    {
        return null;
    }
}
