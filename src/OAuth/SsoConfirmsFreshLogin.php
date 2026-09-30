<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\OAuth;

use HeiHallo\McpKit\Contracts\ConfirmsFreshLogin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For apps that sign people in through a central auth service and keep no
 * password of their own: there is nothing to re-enter, so "confirm it is
 * you" means "sign in at the auth service again". The app's SSO callback
 * calls stamp() after logging the person in; consent needs that stamp to
 * be younger than oauth.confirm_minutes, and otherwise sends the person
 * through the app's login route and back.
 *
 * Bind it with `oauth.confirms => SsoConfirmsFreshLogin::class`.
 */
class SsoConfirmsFreshLogin implements ConfirmsFreshLogin
{
    public const SENT = 'mcp-kit.sso_sent_at';

    /** Record that the person has just signed in at the auth service. */
    public static function stamp(Request $request): void
    {
        $request->session()->put(self::key(), time());
        $request->session()->forget(self::SENT);
    }

    public function required(Request $request): ?Response
    {
        $minutes = config('mcp-kit.oauth.confirm_minutes');

        if ($minutes === null) {
            return null;
        }

        $session = $request->session();
        $signedInAt = (int) $session->get(self::key(), 0);

        if ($signedInAt > 0 && time() - $signedInAt < ((int) $minutes) * 60) {
            return null;
        }

        // Sent to the login a moment ago and back without a stamp: the app's
        // callback does not call stamp(). Say so instead of looping.
        if (time() - (int) $session->get(self::SENT, 0) < 120) {
            $session->forget(self::SENT);

            abort(409, __('This app does not record when you signed in, so the connection cannot be confirmed. Ask a developer to call SsoConfirmsFreshLogin::stamp() after sign-in.'));
        }

        $session->put(self::SENT, time());

        $route = (string) config('mcp-kit.oauth.login_route', 'login');
        $parameters = (array) config('mcp-kit.oauth.login_parameters', []);

        return redirect()->guest(route($route, $parameters));
    }

    protected static function key(): string
    {
        return (string) config('mcp-kit.oauth.login_at_key', 'mcp-kit.login_at');
    }
}
