<?php

declare(strict_types=1);

use HeiHallo\McpKit\Http\Controllers\UploadController;
use HeiHallo\McpKit\Http\Middleware\AuthenticateUploadLink;
use HeiHallo\McpKit\Http\Middleware\EnsureMcpAccess;
use Illuminate\Support\Facades\Route;

/*
 * The same stack as an MCP server route, minus the server: bearer token,
 * the kit's access gate, the kit's throttle. No session, no cookies.
 */
Route::post((string) config('mcp-kit.uploads.route', '/mcp/uploads'), UploadController::class)
    ->middleware(array_filter([
        'auth:sanctum',
        config('mcp-kit.rate_limit.per_minute') ? 'throttle:mcp-kit' : null,
        EnsureMcpAccess::class,
    ]))
    ->name('mcp-kit.uploads');

/*
 * The same endpoint for an assistant that never sees its token — it sits in
 * the connector's settings. request_upload signs a short-lived link; the
 * link stands in for the bearer header and everything after it is the same.
 */
Route::post((string) config('mcp-kit.uploads.link_route', '/mcp/uploads/link'), UploadController::class)
    ->middleware(array_filter([
        AuthenticateUploadLink::class,
        config('mcp-kit.rate_limit.per_minute') ? 'throttle:mcp-kit' : null,
        EnsureMcpAccess::class,
    ]))
    ->name('mcp-kit.uploads.link');
