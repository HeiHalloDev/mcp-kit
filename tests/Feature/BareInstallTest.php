<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('migrates a bare install on an empty database', function () {
    expect(Schema::hasTable('mcp_service_clients'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'assistant_memory'))->toBeTrue()
        ->and(Schema::hasColumn('activity_log', 'source'))->toBeTrue()
        ->and(Schema::hasColumn('activity_log', 'channel'))->toBeTrue()
        ->and(Schema::hasColumn('activity_log', 'token_name'))->toBeTrue();
});

it('binds every contract from its config key', function () {
    expect(app(AbilityCatalogue::class))->toBeInstanceOf(AbilityCatalogue::class)
        ->and(app(PrincipalResolver::class))->toBeInstanceOf(PrincipalResolver::class)
        ->and(app(AuditWriter::class))->toBeInstanceOf(AuditWriter::class)
        ->and(app(GroundRules::class))->toBeInstanceOf(GroundRules::class);
});

it('registers every configured server as a guarded route', function () {
    foreach (app(ServerRegistry::class)->all() as $definition) {
        expect(Route::has($definition->routeName()))->toBeTrue($definition->routeName());
    }
});

it('registers the mcp log channel when the app has none', function () {
    expect(config('logging.channels.mcp.driver'))->toBe('daily');
});
