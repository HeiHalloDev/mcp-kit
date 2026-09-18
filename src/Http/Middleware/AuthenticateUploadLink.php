<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the owner of an upload link back on the request.
 *
 * request_upload signs a link naming the token that asked for it. The
 * signature proves the link was minted here and has not expired; the lookup
 * proves the token behind it still exists and has not run out. EnsureMcpAccess then runs exactly as it does for a bearer upload,
 * so a person blocked, or a token revoked, after the link was handed out
 * stages nothing.
 */
class AuthenticateUploadLink
{
    public function handle(Request $request, Closure $next): Response
    {
        // Checked here rather than by the framework's `signed` middleware,
        // whose bare 403 page tells an assistant nothing it can act on.
        if (! $request->hasValidSignature()) {
            return response()->json([
                'error' => sprintf(
                    'This upload link is not valid: it has expired (links last %d minutes) or was changed. Nothing was stored. Ask for a new link with request_upload.',
                    (int) config('mcp-kit.uploads.link_minutes', 30),
                ),
            ], 403);
        }

        $token = PersonalAccessToken::query()->find($request->query('token'));
        $expires = $token?->expires_at;

        if ($token === null || $token->tokenable === null || ($expires !== null && $expires->isPast())) {
            return response()->json([
                'error' => 'The token behind this upload link is gone or has expired. Nothing was stored. Ask for a new link with request_upload.',
            ], 401);
        }

        $owner = $token->tokenable;

        if (method_exists($owner, 'withAccessToken')) {
            $owner->withAccessToken($token);
        }

        $request->setUserResolver(fn () => $owner);

        return $next($request);
    }
}
