<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools\Concerns;

use HeiHallo\McpKit\Contracts\Links;

/**
 * Staff do not know ids. Every row a tool returns carries the page as a
 * link when one exists; the key is omitted (never null) when it does not.
 */
trait LinksToAdmin
{
    protected function adminUrl(?object $model): ?string
    {
        return $model === null ? null : app(Links::class)->for($model);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function listAdminUrl(string $key, array $filters = []): ?string
    {
        return app(Links::class)->list($key, $filters);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function withAdminUrl(array $row, ?object $model, string $key = 'admin_url'): array
    {
        $url = $this->adminUrl($model);

        if ($url !== null) {
            $row[$key] = $url;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function withListAdminUrl(array $payload, string $listKey, array $filters = []): array
    {
        $url = $this->listAdminUrl($listKey, $filters);

        if ($url !== null) {
            $payload['list_admin_url'] = $url;
        }

        return $payload;
    }
}
