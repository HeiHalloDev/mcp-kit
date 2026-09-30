<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http\Controllers\OAuth;

use HeiHallo\McpKit\OAuth\AuthorizationServer;
use HeiHallo\McpKit\OAuth\OAuthException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RFC 7591. Anyone may register a public client; what they register is
 * narrow — redirects on allowed hosts or loopback, no secret — and a client
 * is worth nothing until a person signs in and says yes to it.
 */
class RegisterController
{
    public function __invoke(Request $request, AuthorizationServer $server): JsonResponse
    {
        try {
            $client = $server->register($request->all(), $request->ip());
        } catch (OAuthException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'client_id' => $client->client_id,
            'client_id_issued_at' => $client->created_at?->getTimestamp(),
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], 201);
    }
}
