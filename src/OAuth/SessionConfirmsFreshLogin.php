<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\OAuth;

use HeiHallo\McpKit\Contracts\ConfirmsFreshLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's own confirmation step: the `password.confirm` route and the
 * `auth.password_confirmed_at` session key it sets (Fortify writes the same
 * key after a passkey or two-factor confirmation). An app without that
 * route has no confirmation step and is let through.
 */
class SessionConfirmsFreshLogin implements ConfirmsFreshLogin
{
    public function required(Request $request): ?Response
    {
        $minutes = config('mcp-kit.oauth.confirm_minutes');

        if ($minutes === null || ! Route::has('password.confirm')) {
            return null;
        }

        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        if ($confirmedAt > 0 && time() - $confirmedAt < ((int) $minutes) * 60) {
            return null;
        }

        return redirect()->guest(route('password.confirm'));
    }
}
