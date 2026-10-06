<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\MemoryStore;
use HeiHallo\McpKit\Events\OnboardingOffered;
use HeiHallo\McpKit\Mcp\Resources\MeResource;
use HeiHallo\McpKit\Mcp\Tools\RememberAboutMeTool;
use HeiHallo\McpKit\Mcp\Tools\WhoAmITool;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use Illuminate\Support\Facades\Event;

test('me describes the person, the team, the token and invites the intro exactly once', function () {
    Event::fake([OnboardingOffered::class]);
    $team = acmeTeam('Support');
    $user = actingWith(acmeUser(['staff', 'things'], 'staff', ['team_id' => $team->id]), ['acme:things:read', 'acme:things:write'], 'laptop');

    $first = AcmeServer::actingAs($user)->resource(MeResource::class)->assertOk();

    $first->assertSee('# Kari Nordmann')
        ->assertSee('Role: staff')
        ->assertSee('Team: Support')
        ->assertSee('Permissions: staff, things')
        ->assertSee('This token can read:')
        ->assertSee('Search and view things (`acme:things:read`)')
        ->assertSee('This token can change:')
        ->assertSee('Nothing remembered yet.')
        ->assertSee('Offer the `getting_started` prompt once')
        ->assertSee('The profile is a hint, not a mode.');

    Event::assertDispatched(OnboardingOffered::class);

    $second = AcmeServer::actingAs($user)->resource(MeResource::class)->assertOk();

    $second->assertDontSee('Offer the `getting_started` prompt once')->assertSee('(getting_started prompt available)');

    expect(app(MemoryStore::class)->get($user)->onboardingOffered())->toBeTrue();
});

test('me renders the memory with the usual-work header and a read-only token notice', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, [
        'routines' => ['Check the inbox first'],
        'handoffs' => ['Invoices go to Ola'],
        'preferences' => ['language' => 'nb'],
        'note' => 'Short answers',
        'confirm' => true,
    ]);

    AcmeServer::actingAs($user)->resource(MeResource::class)
        ->assertOk()
        ->assertSee('## How Kari usually works')
        ->assertSee('Today may differ; help with what is asked.')
        ->assertSee('- Usually: Check the inbox first')
        ->assertSee('- Hands off: Invoices go to Ola')
        ->assertSee('- language: nb')
        ->assertSee('- Short answers')
        ->assertSee('This token cannot change anything.')
        ->assertSee('Update with `remember_about_me`');
});

test('me lists recent activity from the mcp rows', function () {
    Thing::query()->create(['name' => 'Widget']);
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things', [])->assertSuccessful();
    Mcp::call($token, '/mcp/acme', 'list_things', [])->assertSuccessful();

    Mcp::readResource($token, '/mcp/acme', 'acme://me')
        ->assertSuccessful()
        ->assertSee('## Recently (last 14 days)')
        ->assertSee('2 tool calls')
        ->assertSee('Most used: list_things (2)');
});

test('a service client gets no profile and whoami mirrors me when exposed', function () {
    config()->set('mcp-kit.me.expose_as_tool', true);
    $client = ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']);
    $client = $client->withAccessToken($client->createToken('service', ['acme:*'])->accessToken);

    AcmeServer::actingAs($client)->resource(MeResource::class)->assertOk()->assertSee('No person, no profile');

    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->tool(WhoAmITool::class, [])->assertOk()->assertSee('# Kari Nordmann');
});

test('whoami and get_ground_rules are there by default, for clients that never read resources', function () {
    $token = acmeToken(acmeAdmin(), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful()->assertSee('whoami')->assertSee('get_ground_rules');

    $rules = Mcp::readResource($token, '/mcp/acme', 'acme://ground-rules')->assertSuccessful()->json('result.contents.0.text');
    $tool = Mcp::call($token, '/mcp/acme', 'get_ground_rules', [])->assertSuccessful()->json('result.content.0.text');

    expect($tool)->toBe($rules);

    $instructions = Mcp::rpc($token, '/mcp/acme', 'initialize', [
        'protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'h', 'version' => '1'],
    ])->json('result.instructions');

    expect($instructions)->toContain('calling `whoami` and `get_ground_rules`');
});

test('resource_tools off leaves the resources alone and the instructions name them', function () {
    config()->set('mcp-kit.resource_tools', false);
    $token = acmeToken(acmeAdmin(), ['acme:things:read']);

    Mcp::listTools($token, '/mcp/acme')->assertSuccessful()->assertDontSee('whoami')->assertDontSee('get_ground_rules');

    $instructions = Mcp::rpc($token, '/mcp/acme', 'initialize', [
        'protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'h', 'version' => '1'],
    ])->json('result.instructions');

    expect($instructions)->toContain('by reading `acme://me`');
});

test('names and app names reach the model as written, not as HTML entities', function () {
    config()->set('app.name', 'Smith & Sons');
    $user = actingWith(acmeUser(['staff', 'things'], 'staff', ['name' => "D'angelo O'Brien"]), ['acme:things:read']);

    $me = AcmeServer::actingAs($user)->tool(WhoAmITool::class, [])->assertOk()->assertSee("# D'angelo O'Brien");
    $rules = AcmeServer::actingAs($user)->tool(\HeiHallo\McpKit\Mcp\Tools\GetGroundRulesTool::class, [])->assertOk()->assertSee('Smith & Sons');

    $me->assertDontSee('&#039;');
    $rules->assertDontSee('&amp;');
});
