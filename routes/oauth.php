<?php

declare(strict_types=1);

use HeiHallo\McpKit\Http\Controllers\OAuth\AuthorizeController;
use HeiHallo\McpKit\Http\Controllers\OAuth\MetadataController;
use HeiHallo\McpKit\Http\Controllers\OAuth\RegisterController;
use HeiHallo\McpKit\Http\Controllers\OAuth\RevokeController;
use HeiHallo\McpKit\Http\Controllers\OAuth\TokenController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
 * Sign in with a URL (mcp-kit.oauth, mode local). The route names of the
 * protected-resource documents are the ones laravel/mcp looks for: with
 * them registered, every MCP route's 401 carries the WWW-Authenticate
 * header that starts a client's sign-in.
 */
$prefix = trim((string) config('mcp-kit.oauth.route_prefix', 'oauth'), '/');

// Registration is open to anyone, so it is the endpoint worth throttling
// hardest; the token endpoint is throttled against guessing.
RateLimiter::for('mcp-kit-oauth-register', fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('mcp-kit.oauth.register_per_minute', 10)))->by('mcp-kit-oauth-register:'.$request->ip()));
RateLimiter::for('mcp-kit-oauth-token', fn (Request $request): Limit => Limit::perMinute(60)->by('mcp-kit-oauth-token:'.$request->ip()));

Route::get('/.well-known/oauth-protected-resource', [MetadataController::class, 'resource'])
    ->name('mcp.oauth.protected-resource');
Route::get('/.well-known/oauth-protected-resource/{path}', [MetadataController::class, 'resource'])
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource.nested');
Route::get('/.well-known/oauth-authorization-server', [MetadataController::class, 'authorizationServer'])
    ->name('mcp.oauth.authorization-server');
Route::get('/.well-known/oauth-authorization-server/{path}', [MetadataController::class, 'authorizationServer'])
    ->where('path', '.*')
    ->name('mcp.oauth.authorization-server.nested');

Route::post("{$prefix}/register", RegisterController::class)
    ->middleware('throttle:mcp-kit-oauth-register')
    ->name('mcp-kit.oauth.register');

Route::post("{$prefix}/token", TokenController::class)
    ->middleware('throttle:mcp-kit-oauth-token')
    ->name('mcp-kit.oauth.token');

Route::post("{$prefix}/revoke", RevokeController::class)
    ->middleware('throttle:mcp-kit-oauth-token')
    ->name('mcp-kit.oauth.revoke');

Route::middleware((array) config('mcp-kit.oauth.consent_middleware', ['web', 'auth']))->group(function () use ($prefix): void {
    Route::get("{$prefix}/authorize", [AuthorizeController::class, 'show'])->name('mcp-kit.oauth.authorize');
    Route::post("{$prefix}/authorize", [AuthorizeController::class, 'decide'])->name('mcp-kit.oauth.decide');
});
