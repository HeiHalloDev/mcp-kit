<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Events\GapFollowedUp;
use HeiHallo\McpKit\Events\GapReported;
use HeiHallo\McpKit\Events\GapStatusChanged;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Mcp\Resources\GapsResource;
use HeiHallo\McpKit\Mcp\Resources\MeResource;
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

test('the list is for whoever builds the app, not for the staff who report', function () {
    $staff = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, gapArguments([
        'note' => 'Doing it by hand every Monday.',
        'confirm' => true,
    ]));

    // Staff may report. Reading what everybody reported is a developer's job:
    // a narrow token can belong to somebody outside the team entirely.
    AcmeServer::actingAs($staff)
        ->resource(GapsResource::class)
        ->assertHasErrors()
        ->assertSee("developer's job")
        ->assertDontSee('Doing it by hand every Monday.');

    $client = Mcp::token(
        ServiceClient::query()->create(['name' => 'Reporting', 'slug' => 'reporting']),
        ['acme:things:read'],
        'service',
    );

    Mcp::readResource($client, '/mcp/acme', 'acme://gaps')
        ->assertDontSee('Doing it by hand every Monday.');
});

test('the resource ranks blocking and most-reported first, and shows what was settled', function () {
    $user = actingWith(acmeAdmin(), ['acme:things:read']);

    AcmeServer::actingAs($user)->resource(GapsResource::class)->assertSee('Nothing reported');

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

test('the person who reported a gap hears what came of it, once', function () {
    $staff = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    // Nothing has happened to it yet, so there is nothing to pass on.
    AcmeServer::actingAs($staff)
        ->resource(MeResource::class)
        ->assertDontSee('Messages for');

    $admin = actingWith(acmeAdmin(), ['acme:things:read']);
    $id = app(GapStore::class)->list()[0]->id;

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => $id,
        'status' => 'done',
        'resolution' => 'move_signup now takes a target study.',
        'confirm' => true,
    ]);

    // The admin never reported it, so it is not their answer to hear.
    AcmeServer::actingAs($admin)
        ->resource(MeResource::class)
        ->assertDontSee('Messages for');

    AcmeServer::actingAs($staff)
        ->resource(MeResource::class)
        ->assertSee('Messages for')
        ->assertSee('move_signup now takes a target study.');

    // Reading the profile is not telling them: the answer stays until an
    // assistant confirms with message_delivered that it passed it on.
    AcmeServer::actingAs($staff)
        ->resource(MeResource::class)
        ->assertSee('move_signup now takes a target study.');
});

test('a runaway title is cut to fit, never crashed on', function () {
    $user = actingWith(acmeUser(), ['acme:things:read'], 'laptop');
    $sentence = 'the tool cannot '.str_repeat('really ', 60).'do the thing';

    $filed = AcmeServer::actingAs($user)->tool(ReportGapTool::class, gapArguments([
        'title' => $sentence,
        'confirm' => true,
    ]));

    expect($filed)->toHaveExecuted();

    $gaps = app(GapStore::class)->list();

    expect($gaps)->toHaveCount(1)
        ->and(mb_strlen($gaps[0]->title))->toBeLessThanOrEqual(201)
        ->and($gaps[0]->title)->toStartWith('the tool cannot really')
        ->and($gaps[0]->title)->toEndWith('…')
        // The full sentence survives where there is room for it.
        ->and($gaps[0]->need)->toBe(gapArguments()['need']);
});

/**
 * Triage was costing a production database query per question: what came in
 * today, which id is this, and no way at all to merge two rows describing
 * one thing.
 */
test('the list and the filing both carry the id and the dates', function () {
    $user = actingWith(acmeUser(), ['acme:things:read'], 'laptop');

    $filed = AcmeServer::actingAs($user)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    $filed->assertSee('"id"');

    $list = Mcp::readResource(acmeToken(acmeAdmin(), ['acme:*']), '/mcp/acme', 'acme://gaps');

    $list->assertSuccessful();

    expect((string) $list->getContent())->toContain('Reported '.now()->format('Y-m-d'))
        ->and((string) $list->getContent())->toContain('Kari Nordmann');
});

test('a privileged person files and settles in one call, and a plain one cannot', function () {
    Event::fake([GapReported::class, GapStatusChanged::class]);

    $staff = actingWith(acmeUser(), ['acme:things:read'], 'laptop');

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, gapArguments([
        'status' => 'declined',
        'resolution' => 'Lives in the other connection.',
        'confirm' => true,
    ]))->assertHasErrors();

    $admin = actingWith(acmeAdmin(), ['acme:*'], 'laptop');

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, gapArguments([
        'status' => 'declined',
        'resolution' => 'Lives in the other connection: search_contacts answers it.',
        'confirm' => true,
    ]))->assertOk()->assertSee('declined');

    $gaps = app(GapStore::class)->list([Gap::DECLINED]);

    expect($gaps)->toHaveCount(1)
        ->and($gaps[0]->resolution)->toContain('search_contacts')
        ->and($gaps[0]->resolvedBy)->toBe('Ada Admin');

    Event::assertDispatched(GapStatusChanged::class);
});

test('a resolution can be reworded after it was settled', function () {
    $admin = actingWith(acmeAdmin(), ['acme:*'], 'laptop');
    $gap = app(GapStore::class)->put((new Gap(
        key: 'cannot-do-the-thing',
        title: 'cannot do the thing',
        need: 'Doing the thing.',
        missing: 'No tool for it.',
    ))->settled(Gap::PLANNED, 'First draft.', 'Ada Admin'));

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => (string) $gap->id,
        'status' => 'planned',
        'confirm' => true,
    ])->assertHasErrors();

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => (string) $gap->id,
        'status' => 'planned',
        'resolution' => 'Built, waiting to be deployed. It reads the whole branch in one call.',
        'confirm' => true,
    ])->assertOk()->assertSee('one call');

    expect(app(GapStore::class)->find($gap->id)->resolution)->toContain('waiting to be deployed');
});

test('two rows describing one thing are merged, reporters and all', function () {
    $store = app(GapStore::class);
    $admin = actingWith(acmeAdmin(), ['acme:*'], 'laptop');

    $survivor = $store->put(new Gap(
        key: 'no-way-to-read-a-whole-study',
        title: 'no way to read a whole study',
        need: 'Lese et helt studium.',
        missing: 'One page per call.',
        reporters: [['name' => 'Kari Nordmann', 'at' => now()->toIso8601String(), 'note' => '']],
    ));

    $duplicate = $store->put(new Gap(
        key: 'reading-a-study-takes-hundreds-of-calls',
        title: 'reading a study takes hundreds of calls',
        need: 'Lese et helt studium.',
        missing: 'Same thing, said differently.',
        blocking: true,
        reporters: [['name' => 'Per Hansen', 'at' => now()->toIso8601String(), 'note' => 'Ran into the rate limit.']],
    ));

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => (string) $duplicate->id,
        'merge_into' => (string) $survivor->id,
    ])->assertOk()->assertSee('Per Hansen');

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => (string) $duplicate->id,
        'merge_into' => (string) $survivor->id,
        'confirm' => true,
    ])->assertOk();

    $merged = $store->find($survivor->id);
    $closed = $store->find($duplicate->id);

    expect($merged->reports)->toBe(2)
        ->and(array_column($merged->reporters, 'name'))->toBe(['Kari Nordmann', 'Per Hansen'])
        ->and($merged->blocking)->toBeTrue()
        ->and($closed->status)->toBe(Gap::DECLINED)
        ->and($closed->resolution)->toContain('#'.$survivor->id);
});

it('lets privileged staff file a gap as someone else\'s report, so the answer goes to them', function () {
    $admin = acmeToken(acmeAdmin(), ['acme:*']);
    $staff = acmeToken(acmeUser(attributes: ['name' => 'Siri Sender']), ['acme:things:read']);
    acmeUser(attributes: ['name' => 'Benjamin Christensen', 'email' => 'benjamin@example.com']);

    $args = ['title' => 'cannot correct Norwegian movement texts', 'need' => 'Correct them', 'missing' => 'An edit tool', 'for' => 'Benjamin', 'confirm' => true];

    expect(Mcp::call($staff, '/mcp/acme', 'report_gap', $args)->json('result.isError'))->toBeTrue();

    Mcp::call($admin, '/mcp/acme', 'report_gap', $args)->assertSuccessful();

    $gap = app(GapStore::class)->openMatching(Gap::key('cannot correct Norwegian movement texts'));

    expect(array_column($gap->reporters, 'name'))->toBe(['Benjamin Christensen']);
});

/*
 * Follow-ups. A retest or a new example had nowhere to go: the reporter was
 * told the gap was already theirs, and a privileged one wrote it over the
 * resolution of the open gap, where the next note replaced it and the list
 * never showed it.
 */

test('a reporter adds to their own gap, and each note is kept beside the last', function () {
    Event::fake([GapFollowedUp::class]);
    $staff = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    $id = (string) app(GapStore::class)->list()[0]->id;

    $preview = AcmeServer::actingAs($staff)->tool(ReportGapTool::class, ['gap' => $id, 'note' => 'Retested on Friday: still one study at a time.']);

    expect($preview)->toBePreview('Follow up on a gap')
        ->and(app(GapStore::class)->find($id)->followUps)->toBe([]);

    expect(AcmeServer::actingAs($staff)->tool(ReportGapTool::class, [
        'gap' => $id,
        'note' => 'Retested on Friday: still one study at a time.',
        'confirm' => true,
    ]))->toHaveExecuted();

    expect(AcmeServer::actingAs($staff)->tool(ReportGapTool::class, [
        'gap' => $id,
        'note' => 'It also loses the payment plan.',
        'confirm' => true,
    ]))->toHaveExecuted();

    $gap = app(GapStore::class)->find($id);

    expect(array_column($gap->followUps, 'text'))->toBe(['Retested on Friday: still one study at a time.', 'It also loses the payment plan.'])
        ->and(array_column($gap->followUps, 'name'))->toBe(['Kari Nordmann', 'Kari Nordmann'])
        ->and($gap->status)->toBe(Gap::OPEN)
        ->and($gap->resolution)->toBeNull()
        ->and($gap->reports)->toBe(1);

    Event::assertDispatchedTimes(GapFollowedUp::class, 2);
});

test('reporting your own gap again with something to say adds it as a follow-up', function () {
    $staff = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    expect(AcmeServer::actingAs($staff)->tool(ReportGapTool::class, gapArguments([
        'note' => 'Happened again with a second signup.',
        'confirm' => true,
    ])))->toHaveExecuted();

    $gaps = app(GapStore::class)->list();

    expect($gaps)->toHaveCount(1)
        ->and($gaps[0]->reports)->toBe(1)
        ->and($gaps[0]->reporters)->toHaveCount(1)
        ->and(array_column($gaps[0]->followUps, 'text'))->toBe(['Happened again with a second signup.']);
});

test('a colleague who did not report a gap neither writes on it nor reads it', function () {
    $reporter = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($reporter)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    $id = (string) app(GapStore::class)->list()[0]->id;
    $other = actingWith(acmeUser(['staff', 'things'], 'staff', ['name' => 'Ola Nordmann']), ['acme:things:read']);

    AcmeServer::actingAs($other)
        ->tool(ReportGapTool::class, ['gap' => $id, 'note' => 'Me too.', 'confirm' => true])
        ->assertHasErrors();

    AcmeServer::actingAs($other)
        ->tool(ReportGapTool::class, ['gap' => $id])
        ->assertHasErrors()
        ->assertDontSee('Flytte en påmelding');

    expect(app(GapStore::class)->find($id)->followUps)->toBe([]);
});

test('words written on an open gap as its resolution are kept as a follow-up instead', function () {
    $admin = actingWith(acmeAdmin(), ['acme:*'], 'laptop');
    $gap = app(GapStore::class)->put(new Gap(
        key: 'cannot-do-the-thing',
        title: 'cannot do the thing',
        need: 'Doing the thing.',
        missing: 'No tool for it.',
        reporters: [['name' => 'Kari Nordmann', 'at' => now()->toIso8601String(), 'note' => '']],
    ));

    foreach (['Ranking is still wrong with arm support.', 'One-leg and two-leg hops return the same list.'] as $text) {
        expect(AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
            'gap' => (string) $gap->id,
            'status' => 'open',
            'resolution' => $text,
            'confirm' => true,
        ]))->toHaveExecuted();
    }

    $after = app(GapStore::class)->find($gap->id);

    expect($after->resolution)->toBeNull()
        ->and($after->status)->toBe(Gap::OPEN)
        ->and(array_column($after->followUps, 'text'))->toBe(['Ranking is still wrong with arm support.', 'One-leg and two-leg hops return the same list.']);
});

test('the same follow-up sent twice is added once, and a runaway one is refused', function () {
    $staff = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, gapArguments(['confirm' => true]));

    $id = (string) app(GapStore::class)->list()[0]->id;
    $note = ['gap' => $id, 'note' => 'Retested: unchanged.', 'confirm' => true];

    expect(AcmeServer::actingAs($staff)->tool(ReportGapTool::class, $note))->toHaveExecuted();

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, $note)->assertHasErrors();

    AcmeServer::actingAs($staff)
        ->tool(ReportGapTool::class, ['gap' => $id, 'note' => str_repeat('a', Gap::FOLLOW_UP_MAX + 1), 'confirm' => true])
        ->assertHasErrors();

    expect(app(GapStore::class)->find($id)->followUps)->toHaveCount(1);
});

test('a gap is read in full by its id, by the person who reported it', function () {
    $staff = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, gapArguments(['note' => 'Third time this week.', 'confirm' => true]));

    $id = (string) app(GapStore::class)->list()[0]->id;

    AcmeServer::actingAs($staff)->tool(ReportGapTool::class, ['gap' => $id, 'note' => 'Retested: unchanged.', 'confirm' => true]);

    $before = app(GapStore::class)->find($id);

    AcmeServer::actingAs($staff)
        ->tool(ReportGapTool::class, ['gap' => $id])
        ->assertOk()
        ->assertSee('Retested: unchanged.')
        ->assertSee('cannot move a signup between studies')
        // What colleagues said when they reported it is for the builders.
        ->assertDontSee('Third time this week.');

    AcmeServer::actingAs(actingWith(acmeAdmin(), ['acme:things:read']))
        ->tool(ReportGapTool::class, ['gap' => $id])
        ->assertOk()
        ->assertSee('Third time this week.');

    expect(app(GapStore::class)->find($id))->toEqual($before);
});

test('the list shows the follow-ups under the gap, and a note left in the resolution of an open one', function () {
    $store = app(GapStore::class);
    $admin = actingWith(acmeAdmin(), ['acme:things:read']);

    $gap = $store->put((new Gap(
        key: 'cannot-do-the-thing',
        title: 'cannot do the thing',
        need: 'Doing the thing.',
        missing: 'No tool for it.',
        reporters: [['name' => 'Kari Nordmann', 'at' => now()->toIso8601String(), 'note' => '']],
        // How a follow-up was stored before there was anywhere to put one.
        resolution: 'An older note, written over the resolution.',
    ))->followedUp('Kari Nordmann', 'Retested: the arm-support variant still ranks second.'));

    $closed = $store->put((new Gap(
        key: 'no-export',
        title: 'no export',
        need: 'Exporting.',
        missing: 'No export.',
    ))->settled(Gap::DONE, 'Built: export_things.', 'Ada Admin')->followedUp('Kari Nordmann', 'Confirmed working after the deploy.'));

    AcmeServer::actingAs($admin)
        ->resource(GapsResource::class)
        ->assertSee('Retested: the arm-support variant still ranks second.')
        ->assertSee('An older note, written over the resolution.')
        ->assertSee('last follow-up '.now()->format('Y-m-d'))
        ->assertSee('Confirmed working after the deploy.');

    expect($store->find($gap->id)->followUps)->toHaveCount(1)
        ->and($store->find($closed->id)->status)->toBe(Gap::DONE);
});

test('deciding and merging a gap keep what was written on it', function () {
    $store = app(GapStore::class);
    $admin = actingWith(acmeAdmin(), ['acme:*'], 'laptop');

    $survivor = $store->put((new Gap(
        key: 'no-way-to-read-a-whole-study',
        title: 'no way to read a whole study',
        need: 'Lese et helt studium.',
        missing: 'One page per call.',
        reporters: [['name' => 'Kari Nordmann', 'at' => now()->toIso8601String(), 'note' => '']],
    ))->followedUp('Kari Nordmann', 'On the survivor.'));

    $duplicate = $store->put((new Gap(
        key: 'reading-a-study-takes-hundreds-of-calls',
        title: 'reading a study takes hundreds of calls',
        need: 'Lese et helt studium.',
        missing: 'Same thing, said differently.',
        reporters: [['name' => 'Per Hansen', 'at' => now()->toIso8601String(), 'note' => '']],
    ))->followedUp('Per Hansen', 'On the duplicate.'));

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => (string) $duplicate->id,
        'merge_into' => (string) $survivor->id,
        'confirm' => true,
    ])->assertOk();

    AcmeServer::actingAs($admin)->tool(ReportGapTool::class, [
        'gap' => (string) $survivor->id,
        'status' => 'done',
        'resolution' => 'read_study reads it in one call.',
        'confirm' => true,
    ])->assertOk();

    $after = $store->find($survivor->id);

    expect($after->status)->toBe(Gap::DONE)
        ->and(collect($after->followUps)->pluck('text')->sort()->values()->all())->toBe(['On the duplicate.', 'On the survivor.']);
});
