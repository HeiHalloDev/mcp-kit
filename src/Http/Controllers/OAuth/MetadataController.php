<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http\Controllers\OAuth;

use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Http\JsonResponse;

/**
 * The two documents a client reads after its first 401: which
 * authorization server guards this MCP server (RFC 9728), and where that
 * server's endpoints are (RFC 8414). Both point at this same app.
 */
class MetadataController
{
    public function resource(ServerRegistry $servers, ?string $path = null): JsonResponse
    {
        $resource = url('/');
        $name = (string) config('app.name');

        foreach ($servers->all() as $definition) {
            if ($path !== null && trim($path, '/') === $definition->uri()) {
                $resource = $definition->url();
                $name = $definition->label;
            }
        }

        return response()->json([
            'resource' => $resource,
            'resource_name' => $name,
            'authorization_servers' => [url('/')],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => ['mcp'],
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        $prefix = trim((string) config('mcp-kit.oauth.route_prefix', 'oauth'), '/');

        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => url("{$prefix}/authorize"),
            'token_endpoint' => url("{$prefix}/token"),
            'registration_endpoint' => url("{$prefix}/register"),
            'revocation_endpoint' => url("{$prefix}/revoke"),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['mcp'],
        ]);
    }
}
