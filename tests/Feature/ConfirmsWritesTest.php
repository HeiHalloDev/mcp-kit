<?php

declare(strict_types=1);

use HeiHallo\McpKit\Events\WriteConfirmed;
use HeiHallo\McpKit\Events\WritePreviewed;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\UpdateThingTool;
use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Request;
use Spatie\Activitylog\Models\Activity;

test('the bare call previews in one shape and changes nothing', function () {
    Event::fake([WritePreviewed::class]);
    $thing = Thing::query()->create(['name' => 'Widget']);
    $user = actingWith(acmeUser(), ['acme:*'], 'laptop');

    $response = AcmeServer::actingAs($user)->tool(UpdateThingTool::class, ['id' => $thing->id, 'name' => 'Gadget']);

    expect($response)->toBePreview('Rename thing');
    $response->assertSee('"by":"Kari Nordmann (mcp: laptop)"')
        ->assertSee('"next":"Nothing has changed. Show this to the person and call again with confirm=true."')
        ->assertSee('"note":"Nothing has changed.');

    expect($thing->fresh()->name)->toBe('Widget')
        ->and(Activity::query()->where('log_name', 'mcp')->count())->toBe(0);

    Event::assertDispatched(WritePreviewed::class);
});

test('confirm executes, records one mcp row in the owner\'s name and fires the event', function () {
    Event::fake([WriteConfirmed::class]);
    $thing = Thing::query()->create(['name' => 'Widget']);
    $user = actingWith(acmeUser(), ['acme:*'], 'laptop');

    $response = AcmeServer::actingAs($user)->tool(UpdateThingTool::class, ['id' => $thing->id, 'name' => 'Gadget', 'confirm' => true]);

    expect($response)->toHaveExecuted('Rename thing');
    expect($thing->fresh()->name)->toBe('Gadget');

    $row = Activity::query()->where('log_name', 'mcp')->sole();

    expect($row->event)->toBe('executed')
        ->and($row->description)->toBe('Rename thing')
        ->and($row->causer_id)->toBe($user->id)
        ->and($row->subject_id)->toBe($thing->id)
        ->and($row->properties['tool'])->toBe('update_thing')
        ->and($row->properties['arguments']['name'])->toBe('Gadget')
        ->and($row->properties['details']['thing']['from'])->toBe('Widget')
        ->and($row->properties['result']['thing']['name'])->toBe('Gadget')
        ->and($row->channel)->toBe('mcp')
        ->and($row->token_name)->toBe('mcp: laptop');

    // The domain row the tool wrote exists; channel stamping over HTTP is covered in CallRecordingTest.
    expect(Activity::query()->where('log_name', 'things')->count())->toBe(1);

    Event::assertDispatched(WriteConfirmed::class, fn (WriteConfirmed $e): bool => $e->record->action === 'Rename thing');
});

test('a RuntimeException becomes the error message; other exceptions are reported and mapped', function () {
    $thing = Thing::query()->create(['name' => 'Widget']);
    $user = actingWith(acmeUser(), ['acme:*']);

    AcmeServer::actingAs($user)->tool(UpdateThingTool::class, ['id' => $thing->id, 'name' => 'x', 'fail' => 'runtime', 'confirm' => true])
        ->assertHasErrors(['The thing is locked.']);

    AcmeServer::actingAs($user)->tool(UpdateThingTool::class, ['id' => $thing->id, 'name' => 'x', 'fail' => 'boom', 'confirm' => true])
        ->assertHasErrors(['Mapped: something went wrong']);

    expect($thing->fresh()->name)->toBe('Widget')
        ->and(Activity::query()->where('log_name', 'mcp')->count())->toBe(0);
});

test('confirm accepts "true" as a string', function () {
    $thing = Thing::query()->create(['name' => 'Widget']);
    $user = actingWith(acmeUser(), ['acme:*']);

    AcmeServer::actingAs($user)->tool(UpdateThingTool::class, ['id' => $thing->id, 'name' => 'Gadget', 'confirm' => 'true'])->assertSee('"success":true');

    expect($thing->fresh()->name)->toBe('Gadget');
});

test('read-only mode is enforced a second time inside the confirm helper', function () {
    // The server filter hides write tools; a tool reached some other way
    // (a non-kit server, a direct call) still refuses to execute.
    $thing = Thing::query()->create(['name' => 'Widget']);
    $user = actingWith(acmeUser(), ['acme:*']);
    $this->actingAs($user);
    config()->set('mcp-kit.read_only', true);

    $response = app(UpdateThingTool::class)->handle(new Request(['id' => $thing->id, 'name' => 'Again', 'confirm' => true]));

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toContain('read-only mode')
        ->and($thing->fresh()->name)->toBe('Widget');
});
