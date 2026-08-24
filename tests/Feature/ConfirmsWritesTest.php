<?php

declare(strict_types=1);

use HeiHallo\McpKit\Events\WriteConfirmed;
use HeiHallo\McpKit\Events\WritePreviewed;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\UpdateThingTool;
use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use HeiHallo\McpKit\Tools\StaffTool;
use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Sanctum\TransientToken;
use Spatie\Activitylog\Models\Activity;

test('the bare call previews in one shape and changes nothing', function () {
    Event::fake([WritePreviewed::class]);
    $thing = Thing::query()->create(['name' => 'Widget']);
    $user = actingWith(acmeUser(), ['acme:*'], 'laptop');

    $response = AcmeServer::actingAs($user)->tool(UpdateThingTool::class, ['id' => $thing->id, 'name' => 'Gadget']);

    expect($response)->toBePreview('Rename thing');
    $response->assertSee('"by":"Kari Nordmann (mcp: laptop)"')
        ->assertSee('"next":"Nothing has changed. Show this to the person and call again with confirm=true."')
        ->assertDontSee('"note":');

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

    // The domain row written during the call shares the call id and the channel,
    // also without the HTTP middleware: the helper opened the context itself.
    $domain = Activity::query()->where('log_name', 'things')->sole();

    expect($domain->channel)->toBe('mcp')
        ->and($domain->token_name)->toBe('mcp: laptop')
        ->and($domain->properties['call_id'])->toBe($row->properties['call_id'])
        ->and($row->properties['call_id'])->toBeString();

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

test('an in-app agent on the web guard records the write on the chat channel', function () {
    $thing = Thing::query()->create(['name' => 'Widget']);
    $user = acmeUser();
    $user->withAccessToken(new TransientToken);

    $response = AcmeServer::actingAs($user)->tool(UpdateThingTool::class, ['id' => $thing->id, 'name' => 'Gadget', 'confirm' => true]);

    expect($response)->toHaveExecuted('Rename thing');

    $row = Activity::query()->where('log_name', 'mcp')->sole();
    $domain = Activity::query()->where('log_name', 'things')->sole();

    expect($row->channel)->toBe('chat')
        ->and($row->token_name)->toBeNull()
        ->and($row->properties['client'])->toBe('direct')
        ->and($domain->channel)->toBe('chat')
        ->and($domain->properties['call_id'])->toBe($row->properties['call_id']);
});

test('a base class can record a write that bypassed the helper, once', function () {
    $user = actingWith(acmeUser(), ['acme:*'], 'laptop');
    $this->actingAs($user);

    $tool = new class extends StaffTool
    {
        protected string $name = 'direct_write';

        public function handle(Request $request): Response
        {
            $this->recordWrite($request, 'Direct change', ['what' => 'x'], ['done' => true]);
            $this->recordWrite($request, 'Direct change again');

            return Response::json(['recorded' => $this->writeWasRecorded()]);
        }
    };

    $response = $tool->handle(new Request(['confirm' => true]));

    expect((string) $response->content())->toContain('"recorded":true')
        ->and(Activity::query()->where('log_name', 'mcp')->count())->toBe(1)
        ->and(Activity::query()->where('log_name', 'mcp')->sole()->description)->toBe('Direct change');
});
