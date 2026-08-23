<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Principals;

use Closure;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * McpKit::resolvePrincipalUsing(fn (Authenticatable $tokenable, PrincipalResolver $default) => ...).
 */
final class CallbackPrincipalResolver implements PrincipalResolver
{
    /**
     * @param  Closure(Authenticatable, PrincipalResolver): ?Principal  $callback
     */
    public function __construct(
        private Closure $callback,
        private PrincipalResolver $default,
    ) {}

    public function resolve(Authenticatable $tokenable): ?Principal
    {
        return ($this->callback)($tokenable, $this->default);
    }
}
