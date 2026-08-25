<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Events\GapReported;
use HeiHallo\McpKit\Events\GapStatusChanged;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Mcp\Resources\GapsResource;
use HeiHallo\McpKit\Mcp\Tools\ReportGapTool;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

/**
 * @return array<string, mixed>
 */
function gapArguments(array $overrides = []): array
{
    return [
        'title' => 'cannot move a signup between studies',
        'need' => 'Flytte en påmelding fra ett studium til et annet uten å slette den.',
        'missing' => 'There is no tool for it; move_signup only reorders within one study.',
        ...$overrides,
    ];
}

test('it previews, files, and records the report in the person\'s name', function () {
    Event::fake([GapReported::class]);
    $user = actingWith(acmeUser(), ['acme:things:read'], 'laptop');

    $preview = AcmeServer::actingAs($user)->tool(ReportGapTool::class, gapArguments([
        'blocking' => true,
        'note' => 'Third time this week.',
        'server' => 'acme',
    ]));

    expect($preview)->toBePreview('Report a gap');
    $preview->assertSee('new report')->assertSee('stopped the work');

    expect(app(GapStore::class)->list())->toBe([]);

    $filed = AcmeServer::actingAs($user)->tool(ReportGapTool::class, gapArguments([
        'blocking' => true,
        'note' => 'Third time this week.',
        'server' => 'acme',
        'confirm' => true,
    ]));

    expect($filed)->toHaveExecuted();

    $gaps = app(GapStore::class)->list();

    expect($gaps)->toHaveCount(1)
        ->and($gaps[0]->title)->toBe('cannot move a signup between studies')
        ->and($gaps[0]->blocking)->toBeTrue()
        ->and($gaps[0]->reports)->toBe(1)
        ->and($gaps[0]->reporters[0]['name'])->toBe('Kari Nordmann')
        ->and($gaps[0]->reporters[0]['note'])->toBe('Third time this week.')
        ->and($gaps[0]->server)->toBe('acme');

    Event::assertDispatched(GapReported::class, fn (GapReported $e): bool => $e->isNew);

    $row = Activity::query()->where('log_name', 'mcp')->where('event', 'executed')->sole();
    expect($row->causer_id)->toBe($user->id);
});

test('the same gap from someone else gathers weight instead of duplicating', function () {
    $first = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($first)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    $second = actingWith(acmeUser(['staff', 'things'], 'staff', ['name' => 'Ola Nordmann']), ['acme:things:read']);

    $joined = AcmeServer::actingAs($second)->tool(ReportGapTool::class, gapArguments([
        'title' => 'Cannot move a signup between studies!',
        'blocking' => true,
        'note' => 'Same here.',
        'confirm' => true,
    ]));

    expect($joined)->toHaveExecuted();

    $gaps = app(GapStore::class)->list();

    expect($gaps)->toHaveCount(1)
        ->and($gaps[0]->reports)->toBe(2)
        ->and(array_column($gaps[0]->reporters, 'name'))->toBe(['Kari Nordmann', 'Ola Nordmann'])
        // One person blocked is enough to call the whole gap blocking.
        ->and($gaps[0]->blocking)->toBeTrue();
});

test('the same person reporting twice is told it is already theirs', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    AcmeServer::actingAs($user)
        ->tool(ReportGapTool::class, gapArguments(['confirm' => true]))
        ->assertHasErrors()
        ->assertSee('already reported');

    expect(app(GapStore::class)->list())->toHaveCount(1);
});

test('it insists on what was needed and what was missing', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(ReportGapTool::class, ['title' => 'something', 'confirm' => true])
        ->assertHasErrors()
        ->assertSee('trying to get done');

    AcmeServer::actingAs($user)
        ->tool(ReportGapTool::class, ['title' => 'something', 'need' => 'x', 'confirm' => true])
        ->assertHasErrors()
        ->assertSee('what was missing');

    AcmeServer::actingAs($user)
        ->tool(ReportGapTool::class, gapArguments(['server' => 'nope', 'confirm' => true]))
        ->assertHasErrors()
        ->assertSee("no server called 'nope'");

    expect(app(GapStore::class)->list())->toBe([]);
});

test('credentials are refused', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(ReportGapTool::class, gapArguments([
            'missing' => 'I had to log in with password hunter2 instead.',
            'confirm' => true,
        ]))
        ->assertHasErrors()
        ->assertSee('never a credential');
});

test('only privileged staff move a gap, and closing one needs a reason', function () {
    Event::fake([GapStatusChanged::class]);
    $staff = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    $id = app(GapStore::class)->list()[0]->id;

    AcmeServer::actingAs($staff)
        ->tool(ReportGapTool::class, ['gap' => $id, 'status' => 'done', 'confirm' => true])
        ->assertHasErrors()
        ->assertSee('privileged staff');

    $admin = actingWith(acmeAdmin(), ['acme:things:read']);

    AcmeServer::actingAs($admin)
        ->tool(ReportGapTool::class, ['gap' => $id, 'status' => 'done', 'confirm' => true])
        ->assertHasErrors()
        ->assertSee('what was decided');

    // Planning it needs no reason — nothing has been settled yet.
    expect(AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => $id,
        'status' => 'planned',
        'confirm' => true,
    ]))->toHaveExecuted();

    expect(AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => $id,
        'status' => 'done',
        'resolution' => 'move_signup now takes a target study.',
        'confirm' => true,
    ]))->toHaveExecuted();

    $gap = app(GapStore::class)->find($id);

    expect($gap->status)->toBe(Gap::DONE)
        ->and($gap->resolvedBy)->toBe('Ada Admin')
        ->and($gap->resolution)->toBe('move_signup now takes a target study.')
        ->and($gap->resolvedAt)->not->toBeNull();

    Event::assertDispatched(GapStatusChanged::class);
});

test('a closed gap does not swallow the next report of the same thing', function () {
    $admin = actingWith(acmeAdmin(), ['acme:things:read']);

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    $id = app(GapStore::class)->list()[0]->id;

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => $id,
        'status' => 'declined',
        'resolution' => 'Legacy owns this.',
        'confirm' => true,
    ]);

    $user = actingWith(acmeUser(), ['acme:things:read']);

    expect(AcmeServer::actingAs($user)->tool(ReportGapTool::class, gapArguments(['confirm' => true])))
        ->toHaveExecuted();

    expect(app(GapStore::class)->list([Gap::OPEN]))->toHaveCount(1)
        ->and(app(GapStore::class)->list([Gap::DECLINED]))->toHaveCount(1);
});

test('the resource ranks blocking and most-reported first, and shows what was settled', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->resource(GapsResource::class)->assertSee('Nothing open');

    AcmeServer::actingAs($user)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));
    AcmeServer::actingAs($user)->tool(ReportGapTool::class, gapArguments([
        'title' => 'no way to bulk tag contacts',
        'blocking' => true,
        'note' => 'Stops the campaign work.',
        'confirm' => true,
    ]));

    AcmeServer::actingAs($user)
        ->resource(GapsResource::class)
        ->assertSee('no way to bulk tag contacts')
        ->assertSee('Stops the campaign work.')
        ->assertSee('stopped the work');

    // The blocking one comes first — that is the order the resource renders.
    expect(array_column(array_map(
        fn (Gap $gap): array => ['title' => $gap->title],
        app(GapStore::class)->list(),
    ), 'title'))->toBe(['no way to bulk tag contacts', 'cannot move a signup between studies']);
});

test('a service client cannot report a gap', function () {
    $client = Mcp::token(
        ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']),
        ['acme:things:read'],
        'service',
    );

    Mcp::call($client, '/mcp/acme', 'report_gap', gapArguments(['confirm' => true]))
        ->assertSee('for people');

    expect(app(GapStore::class)->list())->toBe([]);
});

test('turning gaps off removes the tool, the resource and the section', function () {
    config()->set('mcp-kit.gaps.enabled', false);

    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(ReportGapTool::class, gapArguments(['confirm' => true]))
        ->assertHasErrors()
        ->assertSee('turned off');

    expect(app(GroundRules::class)->sections(null, 'acme'))
        ->not->toHaveKey('What this app cannot do');
});
