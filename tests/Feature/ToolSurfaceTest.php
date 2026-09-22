<?php

declare(strict_types=1);

use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Testing\Guards;
use PHPUnit\Framework\ExpectationFailedException;

/*
 * The snapshot pins what each tool takes, not only what it is called. A
 * tool can gain a path that reaches further than anything it reached
 * yesterday — a scope, a flag that settles what it finds — without its
 * name changing by a letter, and a pin of names alone stays green through
 * that.
 */

test('the surface holds every tool with the parameters it takes, sorted', function () {
    $surface = app(ToolReference::class)->surface();

    expect(array_keys($surface))->toBe(['acme', 'reports'])
        ->and(array_keys($surface['acme']))->toBe(['admin_only', 'attach_file', 'list_things', 'log_event', 'update_thing'])
        ->and($surface['acme']['update_thing'])->toBe(['confirm', 'fail', 'id', 'name'])
        ->and($surface['reports']['monthly_numbers'])->toBeArray();
});

test('a tool that takes nothing is pinned as taking nothing, not left out', function () {
    $surface = app(ToolReference::class)->surface();

    expect($surface['acme'])->toHaveKey('admin_only')
        ->and($surface['acme']['admin_only'])->toBeArray();
});

test('it names the parameter a tool grew and asks what the tool now does with it', function () {
    $pinned = app(ToolReference::class)->surface();
    unset($pinned['acme']['update_thing'][3]); // the tool used to take id, fail and confirm only
    $pinned['acme']['update_thing'] = array_values($pinned['acme']['update_thing']);

    $message = Guards::changedSurface(app(ToolReference::class)->surface(), $pinned, '/app/tool-inventory.json');

    expect($message)->toContain('acme/update_thing gained: name')
        ->and($message)->toContain('behind a confirmation')
        ->and($message)->toContain('/app/tool-inventory.json')
        // What the parameter means comes before what to type, or the command is all that gets read.
        ->and(strpos($message, 'asked to do'))->toBeLessThan(strpos($message, 'mcp:inventory'));
});

test('a parameter that disappears is its own message, because connected clients are already sending it', function () {
    $pinned = app(ToolReference::class)->surface();
    $pinned['acme']['update_thing'][] = 'reason';

    $message = Guards::changedSurface(app(ToolReference::class)->surface(), $pinned, '/app/tool-inventory.json');

    expect($message)->toContain('acme/update_thing no longer takes: reason')
        ->and($message)->toContain('client already sending one of these breaks')
        ->and($message)->not->toContain('gained:');
});

test('it says nothing when every tool still takes what it took', function () {
    $surface = app(ToolReference::class)->surface();

    expect(Guards::changedSurface($surface, $surface, '/app/tool-inventory.json'))->toBe('');
});

test('a tool the snapshot has never seen is left to the catalogue check', function () {
    $pinned = app(ToolReference::class)->surface();
    unset($pinned['acme']['update_thing']);

    expect(Guards::changedSurface(app(ToolReference::class)->surface(), $pinned, '/app/tool-inventory.json'))->toBe('');
});

test('a snapshot pinned by name alone keeps working and is compared against names', function () {
    $names = app(ToolReference::class)->inventory();

    expect(Guards::changedSurface(app(ToolReference::class)->surface(), $names, '/app/tool-inventory.json'))->toBe('')
        ->and(Guards::comparable(app(ToolReference::class)->surface(), $names))->toBe($names)
        ->and(Guards::pinnedTools($names['acme']))->toBe($names['acme'])
        ->and(Guards::pinnedTools(app(ToolReference::class)->surface()['acme']))->toBe($names['acme']);
});

test('a server the snapshot does not mention follows the shape the rest of it was written in', function () {
    $surface = app(ToolReference::class)->surface();
    $pinnedWithParameters = ['acme' => $surface['acme']];
    $pinnedByName = ['acme' => array_keys($surface['acme'])];

    expect(Guards::comparable($surface, $pinnedWithParameters)['reports'])->toBe($surface['reports'])
        ->and(Guards::comparable($surface, $pinnedByName)['reports'])->toBe(array_keys($surface['reports']));
});

test('mcp:inventory writes the parameters and says how many it pinned', function () {
    $path = app(ToolReference::class)->inventoryPath();

    if (is_file($path)) {
        unlink($path);
    }

    $this->artisan('mcp:inventory')->expectsOutputToContain('parameters.')->assertExitCode(0);

    expect(json_decode((string) file_get_contents($path), true)['acme']['update_thing'])->toBe(['confirm', 'fail', 'id', 'name']);

    $this->artisan('mcp:inventory', ['--check' => true])->assertExitCode(0);

    unlink($path);
});

test('the guard itself fails on a tool that grew a parameter, and names it', function () {
    $path = app(ToolReference::class)->inventoryPath();
    $pinned = app(ToolReference::class)->surface();
    $pinned['acme']['update_thing'] = ['confirm', 'fail', 'id']; // before the tool took a name
    file_put_contents($path, (string) json_encode($pinned));

    expect(fn () => Guards::assertInventory($path))
        ->toThrow(ExpectationFailedException::class, 'acme/update_thing gained: name');

    unlink($path);
});

test('the guard passes against the snapshot mcp:inventory just wrote', function () {
    $path = app(ToolReference::class)->inventoryPath();

    $this->artisan('mcp:inventory')->assertExitCode(0);

    expect(fn () => Guards::assertInventory($path))->not->toThrow(ExpectationFailedException::class);

    unlink($path);
});
