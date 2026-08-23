<?php

declare(strict_types=1);

use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\UpdateThingTool;

beforeEach(function () {
    @unlink(config('mcp-kit.docs.path'));
});

afterEach(function () {
    @unlink(config('mcp-kit.docs.path'));
});

test('the reference lists every tool with server, domain, ability and type', function () {
    $reference = app(ToolReference::class);
    $tools = $reference->tools();

    expect(array_column($tools, 'name'))->toBe(['admin_only', 'list_things', 'log_event', 'update_thing', 'monthly_numbers'])
        ->and($tools[1]['server'])->toBe('acme')
        ->and($tools[1]['domain'])->toBe('General')
        ->and($tools[1]['ability'])->toBe('acme:things:read')
        ->and($tools[1]['writes'])->toBeFalse()
        ->and($tools[3]['annotations'])->toBe(['IsIdempotent'])
        ->and($reference->abilitiesFor(UpdateThingTool::class))->toBe(['acme:things:write'])
        ->and($reference->inventory())->toBe(['acme' => ['admin_only', 'list_things', 'log_event', 'update_thing'], 'reports' => ['monthly_numbers']]);
});

test('mcp:docs writes between the markers, keeps prose and --check fails when stale', function () {
    $path = config('mcp-kit.docs.path');

    $this->artisan('mcp:docs')->expectsOutputToContain('Missing')->assertFailed();

    file_put_contents($path, "---\nnav_title: Tools\n---\n\n# Tools\n\nProse stays.\n\n<!-- generated:tools:start -->\nold\n<!-- generated:tools:end -->\n");

    $this->artisan('mcp:docs', ['--check' => true])->expectsOutputToContain('out of date')->assertFailed();
    $this->artisan('mcp:docs')->expectsOutputToContain('Regenerated 5 tools')->assertSuccessful();

    $document = (string) file_get_contents($path);

    expect($document)->toStartWith("---\nnav_title: Tools")
        ->toContain('Prose stays.')
        ->toContain('5 tools across 2 servers. 2 read, 3 write.')
        ->toContain('| `list_things` | acme | General | `acme:things:read` | Read |')
        ->toContain('| `update_thing` | acme | `acme:things:write` | IsIdempotent | Rename a thing. |')
        ->toContain('except those behind `acme:events:write`')
        ->not->toContain("\nold\n");

    $this->artisan('mcp:docs', ['--check' => true])->expectsOutputToContain('up to date')->assertSuccessful();
});
