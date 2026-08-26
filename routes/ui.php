<?php

declare(strict_types=1);

use HeiHallo\McpKit\Livewire\TokensPage;
use HeiHallo\McpKit\Livewire\UsagePage;
use Illuminate\Support\Facades\Route;

if (config('mcp-kit.ui.tokens_page.enabled')) {
    Route::middleware((array) config('mcp-kit.ui.tokens_page.middleware', ['web', 'auth']))
        ->get((string) config('mcp-kit.ui.tokens_page.path', 'settings/tokens'), TokensPage::class)
        ->name((string) config('mcp-kit.ui.tokens_page.name', 'mcp-kit.tokens'));
}

if (config('mcp-kit.ui.usage_page.enabled')) {
    Route::middleware((array) config('mcp-kit.ui.usage_page.middleware', ['web', 'auth']))
        ->get((string) config('mcp-kit.ui.usage_page.path', 'settings/mcp-usage'), UsagePage::class)
        ->name((string) config('mcp-kit.ui.usage_page.name', 'mcp-kit.usage'));
}
