<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Contracts\PlaybookStore;
use HeiHallo\McpKit\Events\PlaybookForgotten;
use HeiHallo\McpKit\Events\PlaybookSaved;
use HeiHallo\McpKit\Mcp\Prompts\PlaybookPrompt;
use HeiHallo\McpKit\Mcp\Resources\PlaybooksResource;
use HeiHallo\McpKit\Mcp\Tools\SavePlaybookTool;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Playbooks\Playbook;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use HeiHallo\McpKit\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

/**
 * @param  list<string>  $abilities
 */
function savePlaybook(User $user, array $overrides = [], array $abilities = ['acme:things:read']): array
{
    $arguments = [
        'name' => 'weekly digest',
        'description' => 'The numbers I send out on Mondays.',
        'body' => "1. Read the reports for {{ week }}.\n2. Write the three lines that changed.",
        'arguments' => [['name' => 'week', 'description' => 'Which week', 'required' => true]],
        ...$overrides,
    ];

    return [$arguments, $abilities];
}

test('it previews, saves, and shows up as a prompt the person can run', function () {
    Event::fake([PlaybookSaved::class]);
    $user = actingWith(acmeUser(), ['acme:things:read'], 'laptop');
    [$arguments] = savePlaybook($user);

    $preview = AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, $arguments);

    expect($preview)->toBePreview('Save playbook');
    $preview->assertSee('weekly_digest')->assertSee('"visible_to":"you"');

    expect(app(PlaybookStore::class)->ownedBy($user))->toBe([]);

    $saved = AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, [...$arguments, 'confirm' => true]);

    expect($saved)->toHaveExecuted();

    $playbook = app(PlaybookStore::class)->find($user, 'weekly_digest');

    expect($playbook)->not->toBeNull()
        ->and($playbook->title)->toBe('Weekly digest')
        ->and($playbook->arguments)->toBe([['name' => 'week', 'description' => 'Which week', 'required' => true]])
        ->and($playbook->shared)->toBeFalse();

    Event::assertDispatched(PlaybookSaved::class);

    $row = Activity::query()->where('log_name', 'mcp')->where('event', 'executed')->sole();
    expect($row->causer_id)->toBe($user->id);
});

test('a saved playbook is listed and runs with the arguments filled in', function () {
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);
    [$arguments] = savePlaybook($user);

    Mcp::call($token, '/mcp/acme', 'save_playbook', [...$arguments, 'confirm' => true]);

    $listed = Mcp::rpc($token, '/mcp/acme', 'prompts/list');

    $listed->assertOk();
    expect(json_encode($listed->json('result.prompts')))->toContain('weekly_digest');

    $run = Mcp::rpc($token, '/mcp/acme', 'prompts/get', [
        'name' => 'weekly_digest',
        'arguments' => ['week' => 'week 34'],
    ]);

    $run->assertOk();

    $text = json_encode($run->json('result'));

    expect($text)->toContain('week 34')
        ->and($text)->not->toContain('{{ week }}');

    expect(app(PlaybookStore::class)->find($user->fresh(), 'weekly_digest')->uses)->toBe(1);
});

test('a required argument left out is asked for rather than silently blank', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);
    [$arguments] = savePlaybook($user);

    AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, [...$arguments, 'confirm' => true]);

    $playbook = app(PlaybookStore::class)->find($user, 'weekly_digest');

    $response = AcmeServer::actingAs($user)->prompt(new PlaybookPrompt($playbook), []);

    $response->assertHasErrors()->assertSee('week');
});

test('a placeholder nobody declared is refused while the person is still here', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    $response = AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, [
        'name' => 'chase',
        'description' => 'Chase the unpaid ones.',
        'body' => 'Look at {{ month }} and list who has not paid.',
        'confirm' => true,
    ]);

    $response->assertHasErrors()->assertSee('month');

    expect(app(PlaybookStore::class)->ownedBy($user))->toBe([]);
});

test('credentials are refused', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    $response = AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, [
        'name' => 'deploy',
        'description' => 'Ship it.',
        'body' => 'Log in with password hunter2 and press deploy.',
        'confirm' => true,
    ]);

    $response->assertHasErrors()->assertSee('credentials never belong');
});

test('sharing with everyone is privileged, and colleagues get it read-only', function () {
    $staff = actingWith(acmeUser(), ['acme:things:read']);
    [$arguments] = savePlaybook($staff);

    $refused = AcmeServer::actingAs($staff)->tool(SavePlaybookTool::class, [...$arguments, 'shared' => true, 'confirm' => true]);

    $refused->assertHasErrors()->assertSee('privileged staff');

    $admin = actingWith(acmeAdmin(), ['acme:things:read']);

    AcmeServer::actingAs($admin)->tool(SavePlaybookTool::class, [
        ...$arguments,
        'name' => 'team_digest',
        'shared' => true,
        'confirm' => true,
    ]);

    $colleague = actingWith(acmeUser(), ['acme:things:read']);

    $visible = array_map(fn (Playbook $p): string => $p->name, app(PlaybookStore::class)->visibleTo($colleague));

    expect($visible)->toContain('team_digest');

    $stolen = AcmeServer::actingAs($colleague)->tool(SavePlaybookTool::class, [
        'name' => 'team_digest',
        'description' => 'Mine now.',
        'body' => 'Do it my way.',
        'confirm' => true,
    ]);

    $stolen->assertHasErrors()->assertSee('different name');

    $deleted = AcmeServer::actingAs($colleague)->tool(SavePlaybookTool::class, [
        'name' => 'team_digest',
        'delete' => true,
        'confirm' => true,
    ]);

    $deleted->assertHasErrors()->assertSee('not yours to remove');
});

test('a playbook stays hidden from a token that cannot run it, and from other servers', function () {
    $user = acmeUser();
    $full = acmeToken($user, ['acme:things:write'], 'full');

    Mcp::call($full, '/mcp/acme', 'save_playbook', [
        'name' => 'fix_thing',
        'description' => 'Correct a thing.',
        'body' => 'Find the thing, then update it.',
        'abilities' => ['acme:things:write'],
        'servers' => ['acme'],
        'confirm' => true,
    ]);

    expect(json_encode(Mcp::rpc($full, '/mcp/acme', 'prompts/list')->json('result.prompts')))
        ->toContain('fix_thing');

    $readOnly = acmeToken($user, ['acme:things:read'], 'read-only');

    expect(json_encode(Mcp::rpc($readOnly, '/mcp/acme', 'prompts/list')->json('result.prompts')))
        ->not->toContain('fix_thing');

    expect(json_encode(Mcp::rpc($full, '/mcp/reports', 'prompts/list')->json('result.prompts')))
        ->not->toContain('fix_thing');
});

test('it deletes on confirm and says what is going', function () {
    Event::fake([PlaybookForgotten::class]);
    $user = actingWith(acmeUser(), ['acme:things:read']);
    [$arguments] = savePlaybook($user);

    AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, [...$arguments, 'confirm' => true]);

    $preview = AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, ['name' => 'weekly_digest', 'delete' => true]);

    expect($preview)->toBePreview('Forget playbook');
    $preview->assertSee('never');

    expect(app(PlaybookStore::class)->find($user, 'weekly_digest'))->not->toBeNull();

    $gone = AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, ['name' => 'weekly_digest', 'delete' => true, 'confirm' => true]);

    expect($gone)->toHaveExecuted();
    expect(app(PlaybookStore::class)->find($user, 'weekly_digest'))->toBeNull();

    Event::assertDispatched(PlaybookForgotten::class);
});

test('the cap holds and names the way out', function () {
    config()->set('mcp-kit.playbooks.limits.per_person', 2);

    $user = actingWith(acmeUser(), ['acme:things:read']);

    foreach (['one', 'two'] as $name) {
        AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, [
            'name' => $name,
            'description' => 'A thing.',
            'body' => 'Do the thing.',
            'confirm' => true,
        ]);
    }

    $refused = AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, [
        'name' => 'three',
        'description' => 'One too many.',
        'body' => 'Do the thing.',
        'confirm' => true,
    ]);

    $refused->assertHasErrors()->assertSee('delete=true');

    // Replacing one of the two is still fine.
    $replaced = AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, [
        'name' => 'one',
        'description' => 'Reworded.',
        'body' => 'Do the thing, better.',
        'confirm' => true,
    ]);

    expect($replaced)->toHaveExecuted();
});

test('the resource lists what is saved, and says so plainly when nothing is', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->resource(PlaybooksResource::class)->assertSee('Nothing saved yet');

    [$arguments] = savePlaybook($user);
    AcmeServer::actingAs($user)->tool(SavePlaybookTool::class, [...$arguments, 'confirm' => true]);

    AcmeServer::actingAs($user)
        ->resource(PlaybooksResource::class)
        ->assertSee('weekly_digest')
        ->assertSee('Mondays');
});

test('a service client has no playbooks', function () {
    $client = Mcp::token(
        ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']),
        ['acme:things:read'],
        'service',
    );

    Mcp::call($client, '/mcp/acme', 'save_playbook', [
        'name' => 'nope',
        'description' => 'No.',
        'body' => 'No.',
        'confirm' => true,
    ])->assertSee('for people');

    expect(json_encode(Mcp::rpc($client, '/mcp/acme', 'prompts/list')->json('result.prompts')))
        ->not->toContain('nope');
});

test('turning playbooks off removes the tool, the resource and the section', function () {
    config()->set('mcp-kit.playbooks.enabled', false);

    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)
        ->tool(SavePlaybookTool::class, ['name' => 'x', 'description' => 'x', 'body' => 'x', 'confirm' => true])
        ->assertHasErrors()
        ->assertSee('turned off');

    expect(app(GroundRules::class)->sections(null, 'acme'))
        ->not->toHaveKey('Playbooks');
});
