<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before a client is let in on somebody's behalf, the person at the keyboard
 * should have proved it is them a moment ago — not merely have a session
 * from last week. Return a response that sends them to prove it, or null
 * when they recently did (or the app has no such step).
 */
interface ConfirmsFreshLogin
{
    public function required(Request $request): ?Response;
}
