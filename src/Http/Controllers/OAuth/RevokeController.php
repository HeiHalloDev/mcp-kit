<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http\Controllers\OAuth;

use HeiHallo\McpKit\OAuth\AuthorizationServer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * RFC 7009. Answers 200 whatever it was given, so the endpoint says
 * nothing about which tokens exist.
 */
class RevokeController
{
    public function __invoke(Request $request, AuthorizationServer $server): Response
    {
        $token = (string) $request->input('token', '');
        $clientId = (string) $request->input('client_id', '');

        if ($token !== '' && $clientId !== '') {
            $server->revokeToken($clientId, $token);
        }

        return response('', 200);
    }
}
