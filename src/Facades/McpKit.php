<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Routing\Route server(string $key, ?\HeiHallo\McpKit\Servers\ServerDefinition $definition = null)
 * @method static list<string> middleware()
 * @method static void resolvePrincipalUsing(\Closure $callback)
 * @method static void blockedUsing(\Closure $callback)
 * @method static void resolveSourceUsing(\Closure $callback)
 * @method static string scheme()
 *
 * @see \HeiHallo\McpKit\McpKit
 */
class McpKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HeiHallo\McpKit\McpKit::class;
    }
}
