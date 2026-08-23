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
     * @param  Closure(?Principal, ?object): ?string  $callback
     */
    public function __construct(private Closure $callback) {}

    public function source(?Principal $principal, ?object $activity = null): ?string
    {
        return ($this->callback)($principal, $activity);
    }
}
