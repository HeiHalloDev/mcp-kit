<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Testing;

use HeiHallo\McpKit\Contracts\TokenPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Testing\TestResponse;

/**
 * Transport-level helpers: real bearer tokens against POST /mcp/{server}.
 */
final class Mcp
{
    /**
     * A real personal token on the user, returned as the plain text to send.
     *
     * @param  list<string>  $abilities
     */
    public static function token(Authenticatable $user, array $abilities, string $label = 'harness', ?\DateTimeInterface $expiresAt = null): string
    {
        // Every request in production resolves its caller from scratch. A
        // test suite reuses one application, so the guard would otherwise
        // answer the next request as whoever it authenticated first.
        app('auth')->forgetGuards();

        $name = app(TokenPolicy::class)->name($label);

        return $user->createToken($name, $abilities, $expiresAt)->plainTextToken;
    }

    /**
     * The user with a real token attached, for Server::actingAs($user).
     *
     * @param  list<string>  $abilities
     */
    public static function actingWith(Authenticatable $user, array $abilities, string $label = 'harness'): Authenticatable
    {
        app('auth')->forgetGuards();

        $token = $user->createToken(app(TokenPolicy::class)->name($label), $abilities);

        return $user->withAccessToken($token->accessToken);
    }

    public static function listTools(?string $token, string $path): TestResponse
    {
        return self::rpc($token, $path, 'tools/list');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function call(?string $token, string $path, string $tool, array $arguments = []): TestResponse
    {
        return self::rpc($token, $path, 'tools/call', ['name' => $tool, 'arguments' => $arguments]);
    }

    public static function readResource(?string $token, string $path, string $uri): TestResponse
    {
        return self::rpc($token, $path, 'resources/read', ['uri' => $uri]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function rpc(?string $token, string $path, string $method, array $params = []): TestResponse
    {
        app('auth')->forgetGuards();

        return test()->postJson(
            $path,
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params === [] ? (object) [] : $params],
            array_filter([
                'Accept' => 'application/json, text/event-stream',
                'Authorization' => $token ? "Bearer {$token}" : null,
            ]),
        );
    }
}
