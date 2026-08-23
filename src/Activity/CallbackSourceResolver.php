<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Activity;

use Closure;
use HeiHallo\McpKit\Contracts\ResolvesActivitySource;
use HeiHallo\McpKit\Principal;

/**
 * McpKit::resolveSourceUsing(fn (?Principal $principal) => 'flex.example').
 */
final class CallbackSourceResolver implements ResolvesActivitySource
{
    /**
     * @param  Closure(?Principal): ?string  $callback
     */
    public function __construct(private Closure $callback) {}

    public function source(?Principal $principal): ?string
    {
        return ($this->callback)($principal);
    }
}
