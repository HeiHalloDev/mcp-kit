<?php

declare(strict_types=1);

use HeiHallo\McpKit\Http\Controllers\UploadController;
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
