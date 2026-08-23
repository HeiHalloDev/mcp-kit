<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

interface Links
{
    /**
     * The admin page of a record, or null when there is none.
     */
    public function for(object $model): ?string;

    /**
     * A list page with filters applied, or null when the key is unknown.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(string $key, array $filters = []): ?string;
}
