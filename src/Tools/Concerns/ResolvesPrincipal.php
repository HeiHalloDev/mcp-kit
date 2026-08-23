<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools\Concerns;

use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Principal;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait ResolvesPrincipal
{
    protected function principal(Request $request): ?Principal
    {
        $tokenable = $request->user();

        return $tokenable === null ? null : app(PrincipalResolver::class)->resolve($tokenable);
    }

    /**
     * The calling person, or the error response to return instead.
     */
    protected function requirePerson(Request $request): Principal|Response
    {
        $principal = $this->principal($request);

        if ($principal === null) {
            return Response::error('Authentication required.');
        }

        if ($principal->isService()) {
            return Response::error('This tool is for people: a service client has no profile and cannot confirm changes.');
        }

        if ($principal->blocked) {
            return Response::error('Your account is blocked.');
        }

        return $principal;
    }
}
