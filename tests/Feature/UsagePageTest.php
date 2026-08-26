<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Events\GapStatusChanged;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Learning\Task;
use HeiHallo\McpKit\Livewire\UsagePage;
use HeiHallo\McpKit\McpKitServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * The same two records the assistant reads over MCP — what the work was
 * for, and what people could not get — for a person with a browser.
 */
function bootUsageUi(bool $enabled = true): void
{
    config()->set('mcp-kit.ui.enabled', true);
    config()->set('mcp-kit.ui.usage_page.enabled', $enabled);
    config()->set('mcp-kit.learning.enabled', true);
    $provider = new McpKitServiceProvider(app());
    (fn () => $this->registerUi())->call($provider);
}

/**
 * The name list is built lazily, so a route registered mid-test is only
 * visible after a refresh.
 */
function usageRouteExists(): bool
{
    Route::getRoutes()->refreshNameLookups();

    return Route::has('mcp-kit.usage');
}

function aFrame(array $attributes = []): Task
{
    return app(TaskStore::class)->put(new Task(
        purpose: $attributes['purpose'] ?? 'Refunding a module a student bought twice',
        tokenId: $attributes['tokenId'] ?? (string) random_int(1000, 9999),
        userId: '1',
        name: $attributes['name'] ?? 'Kari Nordmann',
        server: $attributes['server'] ?? 'acme',
        outcome: $attributes['outcome'] ?? Task::DONE,
        result: $attributes['result'] ?? null,
        effort: $attributes['effort'] ?? Task::SMOOTH,
        calls: $attributes['calls'] ?? 3,
    ));
}

function aGap(array $attributes = []): Gap
{
    $title = $attributes['title'] ?? 'No way to list a module\'s bookings';

    return app(GapStore::class)->put(new Gap(
        key: Gap::key($title),
        title: $title,
        need: $attributes['need'] ?? 'Kari wanted every booking on one module.',
        missing: $attributes['missing'] ?? 'Bookings can only be read one student at a time.',
        server: $attributes['server'] ?? 'acme',
        blocking: $attributes['blocking'] ?? false,
        reporters: $attributes['reporters'] ?? [['name' => 'Kari Nordmann', 'at' => '2026-08-26', 'note' => '']],
    ));
}

test('it is a developer\'s view, not a leaderboard', function () {
    bootUsageUi();

    $this->actingAs(acmeUser());

    Livewire::test(UsagePage::class)->assertForbidden();
});

test('it leads with what fell short, then with what was won the hard way', function () {
    bootUsageUi();
    $this->actingAs(acmeAdmin());

    aFrame(['purpose' => 'A thing that worked easily']);
    aFrame(['purpose' => 'A thing that fought back', 'effort' => Task::FOUGHT_IT, 'result' => 'Four passes to do one refund.']);
    aFrame(['purpose' => 'A thing that did not', 'outcome' => Task::FAILED, 'effort' => Task::FIDDLY, 'result' => 'No tool for it.']);

    $page = Livewire::test(UsagePage::class)
        ->assertSee('Fell short')
        ->assertSee('should not have been that hard')
        ->assertSee('A thing that fought back');

    $body = $page->html();

    // A success nobody would otherwise look at, put above the successes.
    expect(strpos($body, 'A thing that did not'))
        ->toBeLessThan(strpos($body, 'A thing that fought back'))
        ->and(strpos($body, 'A thing that fought back'))
        ->toBeLessThan(strpos($body, 'A thing that worked easily'));
});

test('the tally says how much of the work anybody put a name to', function () {
    bootUsageUi();
    $this->actingAs(acmeAdmin());

    aFrame(['purpose' => 'Named work']);
    aFrame(['purpose' => '', 'outcome' => Task::OPEN, 'effort' => null, 'calls' => 5]);

    $tally = Livewire::test(UsagePage::class)
        ->assertSee('Never named')
        ->get('tally');

    expect($tally['frames'])->toBe(2)
        ->and($tally['named'])->toBe(1)
        ->and($tally['share'])->toBe(50)
        ->and($tally['calls'])->toBe(8);
});

test('the server filter narrows the frames and the gaps together', function () {
    bootUsageUi();
    $this->actingAs(acmeAdmin());

    aFrame(['purpose' => 'Work on acme', 'server' => 'acme']);
    aFrame(['purpose' => 'Work on reports', 'server' => 'reports']);
    aGap(['title' => 'Something missing on reports', 'server' => 'reports']);

    Livewire::test(UsagePage::class)
        ->set('server', 'reports')
        ->assertSee('Work on reports')
        ->assertDontSee('Work on acme')
        ->assertSee('Something missing on reports');
});

test('deciding a gap needs a reason, because the reporters read it', function () {
    bootUsageUi();
    $this->actingAs(acmeAdmin());

    $gap = aGap();

    Livewire::test(UsagePage::class)
        ->call('startSettling', (string) $gap->id)
        ->set('resolution', '  ')
        ->call('settle', Gap::DONE)
        ->assertHasErrors('resolution');

    expect(app(GapStore::class)->find($gap->id)->status)->toBe(Gap::OPEN);
});

test('deciding a gap is news for everybody who reported it', function () {
    Event::fake([GapStatusChanged::class]);
    bootUsageUi();
    $admin = acmeAdmin();
    $this->actingAs($admin);

    $gap = aGap();

    // Already told once about an earlier decision; a new one is owed again.
    app(GapStore::class)->markHeard($gap, 'Kari Nordmann');
    expect(app(GapStore::class)->settledFor('Kari Nordmann'))->toBe([]);

    Livewire::test(UsagePage::class)
        ->call('startSettling', (string) $gap->id)
        ->set('resolution', 'Built as list_module_bookings.')
        ->call('settle', Gap::DONE)
        ->assertHasNoErrors()
        ->assertSet('settling', null);

    $settled = app(GapStore::class)->find($gap->id);

    expect($settled->status)->toBe(Gap::DONE)
        ->and($settled->resolution)->toBe('Built as list_module_bookings.')
        ->and($settled->resolvedBy)->toBe($admin->name)
        ->and(array_map(fn (Gap $g): string => $g->title, app(GapStore::class)->settledFor('Kari Nordmann')))
        ->toBe([$gap->title]);

    Event::assertDispatched(GapStatusChanged::class, fn (GapStatusChanged $e): bool => $e->from === Gap::OPEN && $e->gap->status === Gap::DONE);
});

test('a gap is never settled without a decision behind it', function () {
    $gap = aGap();

    expect(fn () => $gap->settled(Gap::OPEN, 'Anything', 'Ada'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $gap->settled(Gap::DONE, '   ', 'Ada'))->toThrow(InvalidArgumentException::class);
});

test('the page is absent while the switch is off', function () {
    bootUsageUi(enabled: false);

    expect(usageRouteExists())->toBeFalse();

    bootUsageUi();

    expect(usageRouteExists())->toBeTrue();
});
