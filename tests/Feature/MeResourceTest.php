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
