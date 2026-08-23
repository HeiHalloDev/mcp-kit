<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Links;

use HeiHallo\McpKit\Contracts\Links;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;

/**
 * Model class => route name, list key => route name, from mcp-kit.links_map:
 *
 *   'links_map' => [
 *       'models' => [YourApp\Models\Order::class => 'admin.orders.show'],
 *       'lists' => ['orders' => 'admin.orders.index'],
 *   ]
 */
class RouteLinks implements Links
{
    public function __construct(protected UrlGenerator $url) {}

    public function for(object $model): ?string
    {
        $map = (array) config('mcp-kit.links_map.models', []);

        foreach ($map as $class => $route) {
            if ($model instanceof $class) {
                $key = $model instanceof Model ? $model->getRouteKey() : ($model->id ?? null);

                return $key === null ? null : $this->url->route($route, $key);
            }
        }

        return null;
    }

    public function list(string $key, array $filters = []): ?string
    {
        $route = config("mcp-kit.links_map.lists.{$key}");

        return is_string($route) ? $this->url->route($route, array_filter($filters, fn ($v) => $v !== null && $v !== '')) : null;
    }
}
