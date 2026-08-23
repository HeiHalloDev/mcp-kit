<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http\Middleware;

use Closure;
use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Events\AccessDenied;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a server to callers that may hold a token at all. Four gates,
 * any of which denies:
 *
 * 1. The principal resolves and is not blocked (a blocked person, an
 *    inactive service client) — the owner may do nothing at all.
 * 2. The credential is a real PersonalAccessToken. This rejects Sanctum's
 *    TransientToken, which a session-authenticated user gets and whose
 *    can() answers true for EVERY ability. It is the only thing between a
 *    logged-in browser and full management access, so MCP routes must
 *    never gain session middleware (the kit refuses to boot if they do).
 * 3. The token holds at least one catalogue ability that reaches THIS
 *    server: a reports-only token is turned away at /mcp/crm before the
 *    handshake.
 * 4. The server requires staff and the owner is staff; service clients
 *    are allowed on the server.
 *
 * Runs before the handshake, so an unauthorised caller never sees the
 * instructions or the tool catalogue.
 */
class EnsureMcpAccess
{
    public function __construct(
        protected PrincipalResolver $principals,
        protected AbilityCatalogue $catalogue,
        protected ServerRegistry $servers,
        protected AuditWriter $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tokenable = $request->user();

        if ($tokenable === null) {
            return $this->deny($request, null, 'Authentication required.', 401);
        }

        $principal = $this->principals->resolve($tokenable);

        if ($principal === null) {
            return $this->deny($request, null, 'Token owner is neither a person nor a service client.');
        }

        if ($principal->blocked) {
            return $this->deny($request, $principal, $principal->isService() ? 'Service client is disabled.' : 'Token owner is blocked.');
        }

        if (! $principal->token instanceof PersonalAccessToken) {
            return $this->deny($request, $principal, 'Request is not authenticated with a personal access token.');
        }

        if (! $this->holdsAnyAbility($principal)) {
            return $this->deny($request, $principal, 'Token carries no MCP ability.');
        }

        $definition = $this->servers->forRoute($request->route());

        if ($definition !== null) {
            if (! in_array($definition->key, $this->catalogue->serversFor($principal->abilities()), true)) {
                return $this->deny($request, $principal, "Token carries no ability for the {$definition->key} server.");
            }

            if ($principal->isService() && ! $definition->serviceClients) {
                return $this->deny($request, $principal, "Service clients may not use the {$definition->key} server.");
            }

            if ($definition->requiresStaff && $principal->isPerson() && ! $principal->staff) {
                return $this->deny($request, $principal, "The {$definition->key} server is for staff.");
            }
        }

        if ($principal->isService() && method_exists($tokenable, 'touchLastUsed')) {
            $lastUsed = $tokenable->last_used_at ?? null;

            if ($lastUsed === null || $lastUsed < now()->subHour()) {
                $tokenable->touchLastUsed();
            }
        }

        return $next($request);
    }

    protected function holdsAnyAbility(Principal $principal): bool
    {
        foreach ($principal->abilities() as $ability) {
            if ($ability === '*' || $this->catalogue->exists($ability)) {
                return true;
            }
        }

        return false;
    }

    protected function deny(Request $request, ?Principal $principal, string $reason, int $status = 403): Response
    {
        $this->audit->recordDenial($principal, $reason, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
        ]);

        event(new AccessDenied($principal, $reason, $request));

        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => $status === 401 ? 'Authentication required.' : 'This token is not permitted to access the MCP server.',
            ],
            'id' => null,
        ], $status);
    }
}
