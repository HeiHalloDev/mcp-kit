<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\MemoryStore;
use HeiHallo\McpKit\Events\MemoryUpdated;
use HeiHallo\McpKit\Events\OnboardingCompleted;
use HeiHallo\McpKit\Mcp\Tools\RememberAboutMeTool;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

test('it previews the diff and size, then saves on confirm in the person\'s name', function () {
    Event::fake([MemoryUpdated::class, OnboardingCompleted::class]);
    $user = actingWith(acmeUser(), ['acme:things:read'], 'laptop');

    $preview = AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, [
        'role' => 'Support agent',
        'routines' => ['Check the inbox first', 'Call back leads after lunch'],
        'preferences' => ['language' => 'nb'],
        'note' => 'Prefers short answers',
        'onboarding' => 'completed',
    ]);

    expect($preview)->toBePreview('Remember about you');
    $preview->assertSee('"about":"you"')->assertSee('Support agent')->assertSee('bytes');

    expect(app(MemoryStore::class)->get($user)->isEmpty())->toBeTrue();

    $saved = AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, [
        'role' => 'Support agent',
        'routines' => ['Check the inbox first', 'Call back leads after lunch'],
        'preferences' => ['language' => 'nb'],
        'note' => 'Prefers short answers',
        'onboarding' => 'completed',
        'confirm' => true,
    ]);

    expect($saved)->toHaveExecuted();

    $memory = app(MemoryStore::class)->get($user);

    expect($memory->role)->toBe('Support agent')
        ->and($memory->routines)->toBe(['Check the inbox first', 'Call back leads after lunch'])
        ->and($memory->preferences)->toBe(['language' => 'nb'])
        ->and($memory->notes[0]['text'])->toBe('Prefers short answers')
        ->and($memory->notes[0]['by'])->toBe('Kari Nordmann (mcp: laptop)')
        ->and($memory->onboardingCompleted())->toBeTrue()
        ->and($memory->updatedBy['via'])->toBe('mcp');

    $row = Activity::query()->where('log_name', 'mcp')->where('event', 'executed')->sole();

    expect($row->subject_id)->toBe($user->id)->and($row->causer_id)->toBe($user->id);

    Event::assertDispatched(MemoryUpdated::class);
    Event::assertDispatched(OnboardingCompleted::class);
});

test('lists merge and dedupe, replace replaces, forget removes, null drops a preference', function () {
    $user = actingWith(acmeUser(), ['acme:things:read']);
    $store = app(MemoryStore::class);

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['routines' => ['A', 'B'], 'preferences' => ['tone' => 'short', 'language' => 'nb'], 'confirm' => true]);
    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['routines' => ['B', 'C'], 'preferences' => ['tone' => null], 'confirm' => true]);

    expect($store->get($user)->routines)->toBe(['A', 'B', 'C'])
        ->and($store->get($user)->preferences)->toBe(['language' => 'nb']);

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['routines' => ['Only'], 'replace' => true, 'confirm' => true]);

    expect($store->get($user)->routines)->toBe(['Only']);

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['forget' => ['Only', 'preferences.language'], 'confirm' => true]);

    expect($store->get($user)->routines)->toBe([])->and($store->get($user)->preferences)->toBe([]);

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['role' => 'x', 'note' => 'n', 'confirm' => true]);
    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['forget' => ['everything'], 'confirm' => true]);

    expect($store->get($user)->isEmpty())->toBeTrue();
});

test('it refuses secrets, empty calls, over-limit lists and a full memory without truncating', function () {
    config()->set('mcp-kit.memory.limits.routines', 2);
    config()->set('mcp-kit.memory.max_bytes', 900);
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['note' => 'my password is hunter2'])
        ->assertHasErrors()->assertSee('looks like a password');

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, [])
        ->assertHasErrors()->assertSee('Nothing to remember');

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['routines' => ['a', 'b', 'c']])
        ->assertHasErrors()->assertSee('Too many routines');

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['note' => str_repeat('x', 240), 'confirm' => true])->assertOk();
    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['note' => str_repeat('y', 240), 'confirm' => true])->assertOk();
    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['note' => str_repeat('z', 240)])
        ->assertHasErrors()->assertSee('Memory is full');

    expect(app(MemoryStore::class)->get($user)->notes)->toHaveCount(2);
});

test('a service client has no memory and a person may only change their own unless privileged', function () {
    $client = ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']);
    $client = $client->withAccessToken($client->createToken('service', ['acme:*'])->accessToken);

    AcmeServer::actingAs($client)->tool(RememberAboutMeTool::class, ['role' => 'bot'])
        ->assertHasErrors()->assertSee('for people');

    $other = acmeUser(attributes: ['email' => 'other@example.test', 'name' => 'Ola Other']);
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, ['role' => 'x', 'user' => 'other@example.test'])
        ->assertHasErrors()->assertSee('only change your own');

    $admin = actingWith(acmeAdmin(), ['acme:*']);

    $response = AcmeServer::actingAs($admin)->tool(RememberAboutMeTool::class, ['role' => 'Sales', 'user' => 'other@example.test', 'confirm' => true]);

    expect($response)->toHaveExecuted('Remember about Ola Other');
    expect(app(MemoryStore::class)->get($other)->role)->toBe('Sales');
});
