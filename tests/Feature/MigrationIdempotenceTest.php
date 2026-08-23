<?php

declare(strict_types=1);

use HeiHallo\McpKit\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Schema;

it('runs the package migrations twice without complaint', function () {
    $migrations = dirname(__DIR__, 2).'/database/migrations';

    foreach (glob($migrations.'/*.php') as $file) {
        $migration = require $file;
        $migration->up();
    }

    expect(Schema::hasColumn('users', 'assistant_memory'))->toBeTrue()
        ->and(Schema::hasColumn('activity_log', 'channel'))->toBeTrue()
        ->and(collect(Schema::getIndexes('activity_log'))->pluck('name')->all())->toContain('activity_log_causer_log_created_index', 'activity_log_log_created_index');
});

it('skips the service-client table when the app brings its own model', function () {
    config()->set('mcp-kit.models.service_client', User::class);
    Schema::dropIfExists('mcp_service_clients');

    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_08_23_000001_create_mcp_service_clients_table.php';
    $migration->up();

    expect(Schema::hasTable('mcp_service_clients'))->toBeFalse();
});
