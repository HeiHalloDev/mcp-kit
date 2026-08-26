<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Events\TaskClosed;
use HeiHallo\McpKit\Learning\Task;
use HeiHallo\McpKit\Mcp\Resources\MeResource;
use HeiHallo\McpKit\Mcp\Tools\WorkingOnTool;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Models\Task as TaskModel;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

/**
 * Frames open themselves. Two rounds of instructions asking an assistant
 * to open one before the work produced zero frames against ninety-six
 * real calls — opening one requires predicting that the work will matter,
 * and models are bad at that and good at reacting to what is in front of
 * them. So the middleware groups the calls, and the assistant is asked
 * afterwards for the part only it knows: what it was for, how it went,
 * and how hard it was.
 */
beforeEach(function (): void {
    config()->set('mcp-kit.learning.enabled', true);
});

test('a frame opens itself on the first call and gathers the ones that follow', function () {
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read'], 'laptop');

    Mcp::call($token, '/mcp/acme', 'list_things')->assertOk();

    $tasks = app(TaskStore::class)->recent();

    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]->isUnnamed())->toBeTrue()
        ->and($tasks[0]->isOpen())->toBeTrue()
        ->and($tasks[0]->name)->toBe('Kari Nordmann')
        ->and($tasks[0]->server)->toBe('acme');

    // The call belongs to the frame without the tool knowing about it.
    $row = Activity::query()->where('log_name', 'mcp')->latest('id')->sole();
    expect($row->properties['task'])->toBe((string) $tasks[0]->id);

    Mcp::call($token, '/mcp/acme', 'list_things')->assertOk();

    expect(app(TaskStore::class)->recent())->toHaveCount(1)
        ->and(app(TaskStore::class)->recent()[0]->calls)->toBe(2);
});

test('the nudge arrives in a tool result, once, and only while the frame is unnamed', function () {
    config()->set('mcp-kit.learning.nudge_after', 2);

    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things')->assertDontSee('nobody has named');

    // It lands in the result of the call itself — the one place the
    // assistant is certain to read.
    Mcp::call($token, '/mcp/acme', 'list_things')->assertSee('nobody has named');

    // Once. A nag every call would be worse than silence.
    Mcp::call($token, '/mcp/acme', 'list_things')->assertDontSee('nobody has named');
});

test('the nudge is its own content block, so the tool answer is untouched', function () {
    config()->set('mcp-kit.learning.nudge_after', 1);

    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);

    $content = Mcp::call($token, '/mcp/acme', 'list_things')->json('result.content');

    expect($content)->toHaveCount(2)
        ->and($content[1]['text'])->toContain('nobody has named')
        ->and($content[0]['text'])->not->toContain('nobody has named');
});

test('naming the open frame fills it in rather than starting a second', function () {
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things');

    Mcp::call($token, '/mcp/acme', 'working_on', [
        'purpose' => 'Refunding a module a student bought twice',
    ])->assertSee('Noted.');

    $tasks = app(TaskStore::class)->recent();

    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]->purpose)->toBe('Refunding a module a student bought twice')
        ->and($tasks[0]->isOpen())->toBeTrue();
});

test('closing records the effort, and refuses without one', function () {
    Event::fake([TaskClosed::class]);

    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['outcome' => 'done', 'result' => 'Refunded.'])
        ->assertHasErrors()
        ->assertSee('smooth, fiddly, fought_it');

    AcmeServer::actingAs($user)->tool(WorkingOnTool::class, [
        'purpose' => 'Refunding a module bought twice',
        'outcome' => 'done',
        'effort' => 'fought_it',
        'result' => 'No tool refunds one of two identical purchases, so it took four passes.',
    ])->assertSee('Noted.');

    $task = app(TaskStore::class)->recent()[0];

    expect($task->outcome)->toBe(Task::DONE)
        ->and($task->effort)->toBe(Task::FOUGHT_IT)
        ->and($task->wasHarderThanItShouldBe())->toBeTrue()
        ->and($task->fellShort())->toBeFalse();

    Event::assertDispatched(TaskClosed::class, fn (TaskClosed $e): bool => $e->task->wasHarderThanItShouldBe());
});

test('only a smooth success may close without saying what happened', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['outcome' => 'done', 'effort' => 'fought_it'])
        ->assertHasErrors()
        ->assertSee('what got in the way');

    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['outcome' => 'partly', 'effort' => 'smooth'])
        ->assertHasErrors()
        ->assertSee('what got in the way');

    // A smooth success needs no explanation — there is nothing to explain.
    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['outcome' => 'done', 'effort' => 'smooth'])
        ->assertHasNoErrors();
});

test('a frame closed without ever being named says so', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['outcome' => 'done', 'effort' => 'smooth'])
        ->assertSee('no purpose on it');

    expect(app(TaskStore::class)->recent()[0]->isUnnamed())->toBeTrue();
});

test('two people do not share a frame', function () {
    $kari = acmeUser();
    $ola = acmeUser(['staff', 'things'], 'staff', ['name' => 'Ola Nordmann']);

    $karisToken = acmeToken($kari, ['acme:things:read'], 'kari');
    $olasToken = acmeToken($ola, ['acme:things:read'], 'ola');

    Mcp::call($karisToken, '/mcp/acme', 'list_things');
    Mcp::call($olasToken, '/mcp/acme', 'list_things');

    $tasks = app(TaskStore::class)->recent();

    expect($tasks)->toHaveCount(2)
        ->and(array_map(fn (Task $t): string => $t->name, $tasks))
        ->toContain('Kari Nordmann', 'Ola Nordmann');
});

test('credentials are refused', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(WorkingOnTool::class, ['purpose' => 'Logging in with password hunter2 to check something'])
        ->assertHasErrors()
        ->assertSee('never a credential');
});

test('the usage view leads with what fell short, then with what was won the hard way', function () {
    $staff = acmeUser();
    $staffToken = acmeToken($staff, ['acme:things:read']);

    Mcp::call($staffToken, '/mcp/acme', 'working_on', [
        'purpose' => 'A thing that worked easily', 'outcome' => 'done', 'effort' => 'smooth',
    ]);
    Mcp::call($staffToken, '/mcp/acme', 'working_on', [
        'purpose' => 'A thing that fought back', 'outcome' => 'done', 'effort' => 'fought_it',
        'result' => 'Four passes to do one refund.',
    ]);
    Mcp::call($staffToken, '/mcp/acme', 'working_on', [
        'purpose' => 'A thing that did not', 'outcome' => 'failed', 'effort' => 'fiddly',
        'result' => 'No tool for it.',
    ]);

    Mcp::readResource($staffToken, '/mcp/acme', 'acme://usage')->assertDontSee('A thing that fought back');

    $admin = acmeAdmin();
    $view = Mcp::readResource(acmeToken($admin, ['acme:things:read']), '/mcp/acme', 'acme://usage');

    $view->assertSee('Fell short')
        ->assertSee('should not have been that hard')
        ->assertSee('A thing that fought back');

    $body = $view->getContent();

    // A success nobody would otherwise look at, put above the successes.
    expect(strpos($body, 'A thing that did not'))
        ->toBeLessThan(strpos($body, 'A thing that fought back'))
        ->and(strpos($body, 'A thing that fought back'))
        ->toBeLessThan(strpos($body, 'A thing that worked easily'));
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

test('with learning off nothing opens, nothing is stamped and the tool is absent', function () {
    config()->set('mcp-kit.learning.enabled', false);

    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read'], 'laptop');

    expect(json_encode(Mcp::listTools($token, '/mcp/acme')->json('result.tools')))
        ->not->toContain('working_on');

    expect(app(GroundRules::class)->sections(null, 'acme'))
        ->not->toHaveKey('Recording what the work was for');

    Mcp::call($token, '/mcp/acme', 'list_things')->assertDontSee('nobody has named');

    expect(app(TaskStore::class)->recent())->toBe([]);

    $row = Activity::query()->where('log_name', 'mcp')->latest('id')->first();
    expect($row->properties)->not->toHaveKey('task');
});

test('the connect instructions ask for the naming, and only where recording is on', function () {
    $rules = app(GroundRules::class);

    expect($rules->instructions('acme', 'Staff tools.'))
        ->toContain('working_on')
        ->toContain('how hard it was');

    config()->set('mcp-kit.learning.enabled', false);

    expect($rules->instructions('acme', 'Staff tools.'))
        ->not->toContain('working_on')
        ->toContain('acme://ground-rules');
});

test('prune takes the frames with the calls', function () {
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things');

    TaskModel::query()->update(['created_at' => now()->subDays(200)]);

    $this->artisan('mcp-kit:prune')->assertSuccessful();

    expect(app(TaskStore::class)->recent(days: 365))->toBe([]);
});

test('a service client gets no frame and cannot name one', function () {
    $client = Mcp::token(
        ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']),
        ['acme:things:read'],
        'service',
    );

    Mcp::call($client, '/mcp/acme', 'list_things')->assertOk();

    expect(app(TaskStore::class)->recent())->toBe([]);

    Mcp::call($client, '/mcp/acme', 'working_on', ['purpose' => 'A machine has no purpose to state'])
        ->assertSee('for people');
});
