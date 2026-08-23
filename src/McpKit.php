<?php

declare(strict_types=1);

namespace HeiHallo\McpKit;

use Closure;
use HeiHallo\McpKit\Activity\CallbackSourceResolver;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\ResolvesActivitySource;
use HeiHallo\McpKit\Http\Middleware\AuditMcpCall;
use HeiHallo\McpKit\Http\Middleware\EnsureMcpAccess;
use HeiHallo\McpKit\Principals\CallbackPrincipalResolver;
use HeiHallo\McpKit\Principals\DefaultPrincipalResolver;
use HeiHallo\McpKit\Servers\ServerDefinition;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Route;
use Laravel\Mcp\Facades\Mcp;

/**
 * The entry points an app calls: server registration with the full guard
 * stack, and the closure sugar for the most common overrides.
 */
class McpKit
{
    /**
     * Register a configured server (or an ad-hoc definition) with
     * auth:sanctum, the throttle, EnsureMcpAccess and AuditMcpCall. The
     * only sanctioned way to expose an MCP server in an app using the kit.
     */
    public static function server(string $key, ?ServerDefinition $definition = null): Route
    {
        $definition ??= app(ServerRegistry::class)->get($key);

        if ($definition === null) {
            throw new \InvalidArgumentException("No server '{$key}' under mcp-kit.servers.");
        }

        return Mcp::web($definition->path, $definition->class)
            ->name($definition->routeName())
            ->middleware(static::middleware());
    }

    /**
     * @return list<string>
     */
    public static function middleware(): array
    {
        $stack = ['auth:sanctum'];

        if (config('mcp-kit.routes.throttle') !== null) {
            $stack[] = 'throttle:mcp-kit';
        }

        $stack[] = EnsureMcpAccess::class;
        $stack[] = AuditMcpCall::class;

        foreach ((array) config('mcp-kit.routes.middleware', []) as $middleware) {
            $stack[] = (string) $middleware;
        }

        return $stack;
    }

    /**
     * Replace how a tokenable becomes a Principal. The default resolver is
     * passed in so the closure can delegate.
     *
     * @param  Closure(Authenticatable, PrincipalResolver): ?Principal  $callback
     */
    public static function resolvePrincipalUsing(Closure $callback): void
    {
        $default = app()->make(DefaultPrincipalResolver::class);

        app()->singleton(PrincipalResolver::class, fn (): PrincipalResolver => new CallbackPrincipalResolver($callback, $default));
    }

    /**
     * Decide "blocked" for people: fn ($user) => $user->status !== 'active'.
     *
     * @param  Closure(Authenticatable): bool  $callback
     */
    public static function blockedUsing(Closure $callback): void
    {
        DefaultPrincipalResolver::blockedUsing($callback);
    }

    /**
     * The product stamped on activity rows: fn (?Principal $p, ?object $row) => 'shop'.
     *
     * @param  Closure(?Principal, ?object): ?string  $callback
     */
    public static function resolveSourceUsing(Closure $callback): void
    {
        app()->singleton(ResolvesActivitySource::class, fn (): ResolvesActivitySource => new CallbackSourceResolver($callback));
    }

    public static function scheme(): string
    {
        return (string) config('mcp-kit.scheme', 'app');
    }
}
