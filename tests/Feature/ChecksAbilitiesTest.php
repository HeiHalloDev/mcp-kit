<?php

declare(strict_types=1);

use HeiHallo\McpKit\Events\AbilityDenied;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\AdminOnlyTool;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\ListThingsTool;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\LogEventTool;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\UpdateThingTool;
use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use Illuminate\Support\Facades\Event;

test('a tool refuses a token that lacks the ability and names it', function () {
    Event::fake([AbilityDenied::class]);
    $user = actingWith(acmeUser(), ['acme:events:write']);

    $response = AcmeServer::actingAs($user)->tool(ListThingsTool::class, []);

    expect($response)->toDenyAbility('acme:things:read');
    Event::assertDispatched(AbilityDenied::class, fn (AbilityDenied $e): bool => $e->ability === 'acme:things:read' && $e->tool === 'list_things');
});

test('a server wildcard, a family wildcard and a legacy alias each grant an ordinary ability', function () {
    Thing::query()->create(['name' => 'Widget']);

    foreach (['acme:*', 'acme:things:*', 'old:things:read', '*'] as $ability) {
        $user = actingWith(acmeUser(), [$ability]);

        AcmeServer::actingAs($user)->tool(ListThingsTool::class, [])->assertOk()->assertSee('Widget');
    }
});

test('the wildcard does not grant an explicit-only ability and the message says so', function () {
    $user = actingWith(acmeAdmin(), ['acme:*']);

    AcmeServer::actingAs($user)->tool(AdminOnlyTool::class, [])
        ->assertSee('Required ability: acme:admin')
        ->assertSee('acme:* does not grant this');
});

test('an explicit-only ability needs a privileged owner even when named on the token', function () {
    $user = actingWith(acmeUser(['staff', 'admin']), ['acme:admin']);

    AcmeServer::actingAs($user)->tool(AdminOnlyTool::class, [])->assertSee('requires an Admin role');

    $admin = actingWith(acmeAdmin(), ['acme:admin']);

    expect(AcmeServer::actingAs($admin)->tool(AdminOnlyTool::class, []))->toBePreview('Admin change');
});

test('a tool refuses an owner who lost the permission behind the ability', function () {
    $user = actingWith(acmeUser(['staff']), ['acme:*']);

    AcmeServer::actingAs($user)->tool(ListThingsTool::class, [])
        ->assertSee("no longer holds the 'things' permission")
        ->assertSee('ask an administrator');
});

test('a tool refuses an owner who is no longer staff on a staff server', function () {
    $user = actingWith(acmeUser(['things']), ['acme:*']);

    AcmeServer::actingAs($user)->tool(ListThingsTool::class, [])->assertSee('no longer staff');
});

test('a blocked owner is refused by every tool', function () {
    $user = actingWith(acmeAdmin(['blocked_at' => now()]), ['acme:*']);

    AcmeServer::actingAs($user)->tool(ListThingsTool::class, [])->assertSee('Your account is blocked.');
});

test('a service client may read and perform allowed writes but nothing else', function () {
    $thing = Thing::query()->create(['name' => 'Widget']);
    $client = ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']);
    $client = $client->withAccessToken($client->createToken('service', ['acme:*'])->accessToken);

    AcmeServer::actingAs($client)->tool(ListThingsTool::class, [])->assertOk()->assertSee('Widget');
    AcmeServer::actingAs($client)->tool(LogEventTool::class, ['event' => 'x'])->assertOk()->assertSee('logged');
    AcmeServer::actingAs($client)->tool(UpdateThingTool::class, ['id' => $thing->id, 'name' => 'Gadget'])
        ->assertSee('belongs to a service client')
        ->assertSee('Writes need a personal token');
});
