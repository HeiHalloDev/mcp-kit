<?php

declare(strict_types=1);
use HeiHallo\McpKit\McpKitServiceProvider;

it('ships neutral defaults', function () {
    $defaults = require dirname(__DIR__, 2).'/config/mcp-kit.php';

    expect($defaults['scheme'])->toBe('app')
        ->and($defaults['servers'])->toBe([])
        ->and($defaults['catalogue']['abilities'])->toBe([])
        ->and($defaults['tokens'])->toBe(['name_prefix' => 'mcp: ', 'default_days' => 90, 'max_days' => 365, 'default_preset' => 'work', 'staff_only' => false, 'wildcards_require_privileged' => true])
        ->and($defaults['routes']['enforce'])->toBeTrue()
        ->and($defaults['read_only'])->toBeFalse()
        ->and(array_keys($defaults['token_presets']))->toBe(['read', 'work', 'full'])
        ->and($defaults['activity']['log_name'])->toBe('mcp')
        ->and($defaults['memory']['column'])->toBe('assistant_memory');
});

it('backfills config keys an app published before they existed', function () {
    config()->set('mcp-kit.tokens', ['name_prefix' => 'ai: ']);
    config()->set('mcp-kit.memory', ['column' => 'memory']);

    $provider = new McpKitServiceProvider(app());
    (fn () => $this->backfillRegistryDefaults())->call($provider);

    expect(config('mcp-kit.tokens.name_prefix'))->toBe('ai: ')
        ->and(config('mcp-kit.tokens.default_days'))->toBe(90)
        ->and(config('mcp-kit.memory.column'))->toBe('memory')
        ->and(config('mcp-kit.memory.limits.notes'))->toBe(20);
});
