<?php

declare(strict_types=1);

use HeiHallo\McpKit\Livewire\ConnectedApps;
use HeiHallo\McpKit\Livewire\TokensPage;
use HeiHallo\McpKit\Livewire\UsagePage;
use Illuminate\Support\Facades\Route;

if (config('mcp-kit.ui.tokens_page.enabled')) {
    $connectPath = (string) config('mcp-kit.ui.tokens_page.path', 'settings/connect');

    Route::middleware((array) config('mcp-kit.ui.tokens_page.middleware', ['web', 'auth']))
        ->get($connectPath, TokensPage::class)
        ->name((string) config('mcp-kit.ui.tokens_page.name', 'mcp-kit.tokens'));

    // Where the page used to be, so bookmarks and old links still land.
    foreach ((array) config('mcp-kit.ui.tokens_page.redirect_from', []) as $oldPath) {
        Route::permanentRedirect((string) $oldPath, '/'.ltrim($connectPath, '/'));
    }
}

if (config('mcp-kit.ui.usage_page.enabled')) {
    Route::middleware((array) config('mcp-kit.ui.usage_page.middleware', ['web', 'auth']))
        ->get((string) config('mcp-kit.ui.usage_page.path', 'settings/mcp-usage'), UsagePage::class)
        ->name((string) config('mcp-kit.ui.usage_page.name', 'mcp-kit.usage'));
}

if (config('mcp-kit.ui.connected_apps_page.enabled')) {
    $redirectTo = config('mcp-kit.ui.connected_apps_page.redirect_to');
    $connectedApps = Route::middleware((array) config('mcp-kit.ui.connected_apps_page.middleware', ['web', 'auth']));

    // The Connect page lists the sign-ins itself: the old page, and the
    // links to it, send people there.
    is_string($redirectTo) && $redirectTo !== ''
        ? $connectedApps->get((string) config('mcp-kit.ui.connected_apps_page.path', 'settings/connected-apps'), fn () => redirect()->route($redirectTo, ['tab' => 'mcp']))
            ->name((string) config('mcp-kit.ui.connected_apps_page.name', 'mcp-kit.connected-apps'))
        : $connectedApps->get((string) config('mcp-kit.ui.connected_apps_page.path', 'settings/connected-apps'), ConnectedApps::class)
            ->name((string) config('mcp-kit.ui.connected_apps_page.name', 'mcp-kit.connected-apps'));
}
