<?php

declare(strict_types=1);

use HeiHallo\McpKit\Livewire\TokensPage;
use Illuminate\Support\Facades\Route;

Route::middleware((array) config('mcp-kit.ui.tokens_page.middleware', ['web', 'auth']))
    ->get((string) config('mcp-kit.ui.tokens_page.path', 'settings/tokens'), TokensPage::class)
    ->name((string) config('mcp-kit.ui.tokens_page.name', 'mcp-kit.tokens'));
