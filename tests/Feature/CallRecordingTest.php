<?php

declare(strict_types=1);

use HeiHallo\McpKit\Audit\NullAuditWriter;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Events\ToolCallRecorded;
use HeiHallo\McpKit\McpKit;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

test('a read call becomes one mcp row with the sanitised arguments', function () {
    Event::fake([ToolCallRecorded::class]);
    Thing::query()->create(['name' => 'Widget']);
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read'], 'laptop');

    Mcp::call($token, '/mcp/acme', 'list_things', ['query' => 'Wid', 'password' => 'hunter2'])->assertSuccessful();

    $row = Activity::query()->where('log_name', 'mcp')->sole();

    expect($row->event)->toBe('read')
        ->and($row->description)->toBe('list_things')
        ->and($row->causer_id)->toBe($user->id)
        ->and($row->channel)->toBe('mcp')
        ->and($row->token_name)->toBe('mcp: laptop')
        ->and($row->properties['tool'])->toBe('list_things')
        ->and($row->properties['server'])->toBe('acme')
        ->and($row->properties['arguments']['query'])->toBe('Wid')
        ->and($row->properties['arguments']['password'])->toBe('[REDACTED]')
        ->and($row->properties['call_id'])->toBeString()
        ->and($row->properties['duration_ms'])->toBeNumeric()
        ->and($row->properties['client'])->toBeString();

    Event::assertDispatched(ToolCallRecorded::class, fn (ToolCallRecorded $e): bool => $e->record->status === 'read' && $e->record->tool === 'list_things');
});

test('a preview becomes a previewed row; a confirmed write stays one executed row with the call id shared by domain rows', function () {
    $thing = Thing::query()->create(['name' => 'Widget']);
    $user = acmeUser();
    $token = acmeToken($user, ['acme:*'], 'laptop');

    Mcp::call($token, '/mcp/acme', 'update_thing', ['id' => $thing->id, 'name' => 'Gadget'])->assertSuccessful();

    expect(Activity::query()->where('log_name', 'mcp')->where('event', 'previewed')->count())->toBe(1);

    Mcp::call($token, '/mcp/acme', 'update_thing', ['id' => $thing->id, 'name' => 'Gadget', 'confirm' => true])->assertSuccessful();

    $executed = Activity::query()->where('log_name', 'mcp')->where('event', 'executed')->get();

    expect($executed)->toHaveCount(1)
        ->and($executed[0]->description)->toBe('Rename thing')
        ->and($executed[0]->properties['duration_ms'])->toBeNumeric()
        ->and($executed[0]->properties['details']['thing']['to'])->toBe('Gadget');

    $domain = Activity::query()->where('log_name', 'things')->sole();

    expect($domain->properties['call_id'])->toBe($executed[0]->properties['call_id'])
        ->and($domain->channel)->toBe('mcp')
        ->and($domain->token_name)->toBe('mcp: laptop')
        ->and(Activity::query()->where('log_name', 'mcp')->count())->toBe(2);
});

test('a denied ability becomes a denied row naming the ability', function () {
    $token = acmeToken(acmeUser(), ['acme:events:write']);

    Mcp::call($token, '/mcp/acme', 'list_things', [])->assertSuccessful();

    $row = Activity::query()->where('log_name', 'mcp')->sole();

    expect($row->event)->toBe('denied')
        ->and($row->properties['ability'])->toBe('acme:things:read')
        ->and($row->properties['reason'])->toContain('Required ability');
});

test('protocol chatter is not recorded', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful();
    Mcp::rpc($token, '/mcp/acme', 'resources/list')->assertSuccessful();

    expect(Activity::query()->where('log_name', 'mcp')->count())->toBe(0);
});

test('the null writer records nothing', function () {
    config()->set('mcp-kit.audit', NullAuditWriter::class);
    app()->forgetInstance(AuditWriter::class);
    Thing::query()->create(['name' => 'Widget']);
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things', [])->assertSuccessful();

    expect(Activity::query()->count())->toBe(0);
});

test('a source resolver stamps the product on rows', function () {
    McpKit::resolveSourceUsing(fn ($principal): ?string => 'acme.example');
    Thing::query()->create(['name' => 'Widget']);
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things', [])->assertSuccessful();

    expect(Activity::query()->where('log_name', 'mcp')->sole()->source)->toBe('acme.example');
});
