<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http\Controllers\OAuth;

use HeiHallo\McpKit\OAuth\AuthorizationServer;
use HeiHallo\McpKit\OAuth\OAuthException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController
{
    public function __invoke(Request $request, AuthorizationServer $server): JsonResponse
    {
        try {
            $clientId = (string) $request->input('client_id', '');

            if ($clientId === '') {
                throw new OAuthException('invalid_client', 'client_id is required.', 401);
            }

            $response = match ($request->input('grant_type')) {
                'authorization_code' => $server->exchangeCode(
                    $clientId,
                    (string) $request->input('code', ''),
                    (string) $request->input('redirect_uri', ''),
                    (string) $request->input('code_verifier', ''),
                ),
                'refresh_token' => $server->refresh($clientId, (string) $request->input('refresh_token', '')),
                default => throw new OAuthException('unsupported_grant_type', 'Only authorization_code and refresh_token are supported.'),
            };
        } catch (OAuthException $e) {
            return response()->json($e->toArray(), $e->status)->withHeaders(['Cache-Control' => 'no-store']);
        }

        return response()->json($response)->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }
}
