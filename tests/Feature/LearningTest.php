<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Events\TaskClosed;
use HeiHallo\McpKit\Events\TaskOpened;
use HeiHallo\McpKit\Learning\Task;
use HeiHallo\McpKit\Mcp\Resources\MeResource;
use HeiHallo\McpKit\Mcp\Tools\WorkingOnTool;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Models\Task as TaskModel;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    config()->set('mcp-kit.learning.enabled', true);
});

test('a frame is opened, the calls inside it are stamped, and closing records the outcome', function () {
    Event::fake([TaskOpened::class, TaskClosed::class]);

    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read'], 'laptop');

    Mcp::call($token, '/mcp/acme', 'working_on', [
        'purpose' => 'Refunding a module a student bought twice',
    ])->assertSee('Noted.');

    $task = app(TaskStore::class)->recent()[0];

    expect($task->purpose)->toBe('Refunding a module a student bought twice')
        ->and($task->isOpen())->toBeTrue()
        ->and($task->name)->toBe('Kari Nordmann')
        ->and($task->server)->toBe('acme');

    Mcp::call($token, '/mcp/acme', 'list_things')->assertOk();

    // The call in between belongs to the frame without the tool knowing.
    $row = Activity::query()
        ->where('log_name', 'mcp')
        ->whereJsonContains('properties->tool', 'list_things')
        ->sole();

    expect($row->properties['task'])->toBe((string) $task->id);

    Mcp::call($token, '/mcp/acme', 'working_on', [
        'outcome' => 'partly',
        'result' => 'Found the duplicate but there is no way to refund only one of two identical purchases.',
    ])->assertSee('Noted.');

    $closed = app(TaskStore::class)->recent()[0];

    expect($closed->outcome)->toBe(Task::PARTLY)
        ->and($closed->fellShort())->toBeTrue()
        ->and($closed->result)->toContain('no way to refund')
        ->and($closed->calls)->toBeGreaterThan(0)
        ->and($closed->closedAt)->not->toBeNull();

    Event::assertDispatched(TaskOpened::class);
    Event::assertDispatched(TaskClosed::class, fn (TaskClosed $e): bool => $e->task->fellShort());
});

test('falling short without saying why is refused', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['purpose' => 'Something involved']);

    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['outcome' => 'failed'])
        ->assertHasErrors()
        ->assertSee('what got in the way');

    // 'done' needs no explanation — there is nothing to explain.
    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['outcome' => 'done'])
        ->assertSee('Noted.');

    expect(app(TaskStore::class)->recent()[0]->outcome)->toBe(Task::DONE);
});

test('opening a second frame closes the abandoned one as unknown rather than as a success', function () {
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'working_on', ['purpose' => 'First thing']);

    Mcp::call($token, '/mcp/acme', 'working_on', ['purpose' => 'Second thing'])
        ->assertSee('recorded as unknown');

    $tasks = app(TaskStore::class)->recent();

    expect($tasks)->toHaveCount(2)
        ->and($tasks[0]->purpose)->toBe('Second thing')
        ->and($tasks[0]->isOpen())->toBeTrue()
        ->and($tasks[1]->purpose)->toBe('First thing')
        ->and($tasks[1]->outcome)->toBe(Task::UNKNOWN);
});

test('closing with nothing open says so instead of inventing a frame', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['outcome' => 'done'])
        ->assertHasErrors()
        ->assertSee('Nothing is open');

    expect(app(TaskStore::class)->recent())->toBe([]);
});

test('two people do not share a frame', function () {
    $kari = acmeUser();
    $ola = acmeUser(['staff', 'things'], 'staff', ['name' => 'Ola Nordmann']);

    $karisToken = acmeToken($kari, ['acme:things:read'], 'kari');
    $olasToken = acmeToken($ola, ['acme:things:read'], 'ola');

    Mcp::call($karisToken, '/mcp/acme', 'working_on', ['purpose' => 'Kari is doing this']);

    // Ola has nothing open, even though Kari does.
    Mcp::call($olasToken, '/mcp/acme', 'working_on', ['outcome' => 'done'])
        ->assertSee('Nothing is open');

    expect(app(TaskStore::class)->openFor((string) $kari->tokens()->first()->id)?->purpose)
        ->toBe('Kari is doing this');
});

test('credentials are refused', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['purpose' => 'Logging in with password hunter2 to check something'])
        ->assertHasErrors()
        ->assertSee('never a credential');
});

test('the usage view is a developer view and puts the shortfalls first', function () {
    $staff = acmeUser();
    $staffToken = acmeToken($staff, ['acme:things:read']);

    Mcp::call($staffToken, '/mcp/acme', 'working_on', ['purpose' => 'A thing that worked']);
    Mcp::call($staffToken, '/mcp/acme', 'working_on', ['outcome' => 'done']);
    Mcp::call($staffToken, '/mcp/acme', 'working_on', ['purpose' => 'A thing that did not']);
    Mcp::call($staffToken, '/mcp/acme', 'working_on', ['outcome' => 'failed', 'result' => 'No tool for it.']);

    Mcp::readResource($staffToken, '/mcp/acme', 'acme://usage')
        ->assertDontSee('A thing that did not');

    $admin = acmeAdmin();
    $adminToken = acmeToken($admin, ['acme:things:read']);

    $view = Mcp::readResource($adminToken, '/mcp/acme', 'acme://usage');

    $view->assertSee('Fell short')->assertSee('No tool for it.')->assertSee('A thing that worked');

    $body = $view->getContent();

    expect(strpos($body, 'A thing that did not'))->toBeLessThan(strpos($body, 'A thing that worked'));
});

test('me tells the person their work is being recorded, and stays quiet when it is not', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->resource(MeResource::class)
        ->assertSee('What is recorded about your work here');

    config()->set('mcp-kit.learning.enabled', false);

    AcmeServer::actingAs($user)
        ->resource(MeResource::class)
        ->assertDontSee('What is recorded about your work here');
});

test('with learning off the tool, the view and the section are all absent', function () {
    config()->set('mcp-kit.learning.enabled', false);

    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read'], 'laptop');

    expect(json_encode(Mcp::listTools($token, '/mcp/acme')->json('result.tools')))
        ->not->toContain('working_on');

    expect(app(GroundRules::class)->sections(null, 'acme'))
        ->not->toHaveKey('Recording what the work was for');

    // Nothing is stamped either.
    Mcp::call($token, '/mcp/acme', 'list_things');

    $row = Activity::query()->where('log_name', 'mcp')->latest('id')->first();

    expect($row->properties)->not->toHaveKey('task');
});

test('a listed tool still works when nobody opened a frame', function () {
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things')->assertOk();

    $row = Activity::query()->where('log_name', 'mcp')->sole();

    expect($row->properties)->not->toHaveKey('task');
});

test('prune takes the frames with the calls', function () {
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'working_on', ['purpose' => 'Old work']);

    TaskModel::query()->update(['created_at' => now()->subDays(200)]);

    $this->artisan('mcp-kit:prune')->assertSuccessful();

    expect(app(TaskStore::class)->recent(days: 365))->toBe([]);
});

test('the tool is unavailable to a service client', function () {
    $client = Mcp::token(
        ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']),
        ['acme:things:read'],
        'service',
    );

    Mcp::call($client, '/mcp/acme', 'working_on', ['purpose' => 'A machine has no purpose to state'])
        ->assertSee('for people');

    expect(app(TaskStore::class)->recent())->toBe([]);
});

test('the connect instructions tell the assistant to open a frame, and only where recording is on', function () {
    $rules = app(GroundRules::class);

    // The ground-rules resource is read late or not at all, so the one
    // thing that must happen before the work is said at connect.
    expect($rules->instructions('acme', 'Staff tools.'))
        ->toContain('working_on')
        ->toContain('an outcome');

    config()->set('mcp-kit.learning.enabled', false);

    expect($rules->instructions('acme', 'Staff tools.'))
        ->not->toContain('working_on')
        // The rest of the footer is untouched for apps that do not record.
        ->toContain('acme://ground-rules');
});
