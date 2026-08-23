<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\MemoryStore;
use HeiHallo\McpKit\Exceptions\UiDependenciesMissing;
use HeiHallo\McpKit\Livewire\AssistantMemory;
use HeiHallo\McpKit\Livewire\TokensPage;
use HeiHallo\McpKit\McpKitServiceProvider;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

function bootUi(): void
{
    config()->set('mcp-kit.ui.enabled', true);
    config()->set('mcp-kit.ui.tokens_page.enabled', true);
    $provider = new McpKitServiceProvider(app());
    (fn () => $this->registerUi())->call($provider);
}

test('the tokens page mints with a preset and extras, lists, and revokes', function () {
    bootUi();
    $admin = acmeAdmin();
    $this->actingAs($admin);

    $component = Livewire::test(TokensPage::class)
        ->assertSee('API tokens')
        ->assertSet('preset', 'read')
        ->set('name', 'Laptop')
        ->set('preset', 'full')
        ->set('extras', ['acme:admin'])
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('shown only once')
        ->assertSee('claude mcp add acme ')
        ->assertSee('claude mcp add acme-reports ');

    $token = $admin->tokens()->sole();

    expect($token->name)->toBe('mcp: Laptop')
        ->and($token->abilities)->toBe(['acme:*', 'reports:*', 'acme:admin'])
        ->and($token->expires_at)->not->toBeNull()
        ->and($component->get('plainTextToken'))->toBeString();

    $component->assertSee('Full + admin')->call('revoke', $token->id);

    expect($admin->tokens()->count())->toBe(0);
});

test('a staff member sees only their own tokens and cannot mint extras', function () {
    bootUi();
    $admin = acmeAdmin();
    $admin->createToken('mcp: admins', ['acme:*']);
    $user = acmeUser();
    $user->createToken('mcp: mine', ['acme:things:read']);
    $this->actingAs($user);

    Livewire::test(TokensPage::class)
        ->assertSee('mine')
        ->assertDontSee('admins')
        ->set('name', 'Phone')
        ->set('preset', 'work')
        ->set('extras', ['acme:admin'])
        ->call('create')
        ->assertHasNoErrors();

    expect($user->tokens()->latest('id')->first()->abilities)->toBe(['acme:things:read', 'acme:things:write', 'acme:events:write']);

    Livewire::test(TokensPage::class)->call('revoke', $admin->tokens()->first()->id)->assertForbidden();
});

test('the tokens route is registered when enabled and absent when not', function () {
    expect(Route::has('mcp-kit.tokens'))->toBeFalse();

    bootUi();
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('mcp-kit.tokens'))->toBeTrue();

    $route = Route::getRoutes()->getByName('mcp-kit.tokens');

    expect($route->uri())->toBe('settings/tokens')->and($route->middleware())->toBe(['web', 'auth']);
});

test('the assistant-memory component shows what is remembered and clears it', function () {
    bootUi();
    $user = acmeUser();
    $this->actingAs($user);
    $store = app(MemoryStore::class);
    $store->put($user, HeiHallo\McpKit\Memory\AssistantMemory::fromArray(['role' => 'Support agent', 'routines' => ['Inbox first']]));

    Livewire::test(AssistantMemory::class)
        ->assertSee('Support agent')
        ->assertSee('Inbox first')
        ->call('neverOffer')
        ->call('forgetEverything');

    $memory = $store->get($user);

    expect($memory->isEmpty())->toBeTrue()->and($memory->onboardingDeclined())->toBeTrue();

    Livewire::test(AssistantMemory::class)->assertSee('Nothing remembered yet.')->call('offerAgain');

    expect($store->get($user)->onboarding)->toBe([]);
});

test('the UI refuses to boot without Livewire and Flux when dependencies are checked', function () {
    config()->set('mcp-kit.ui.enabled', true);
    config()->set('mcp-kit.ui.check_dependencies', true);
    $provider = new McpKitServiceProvider(app());

    expect(fn () => (fn () => $this->registerUi())->call($provider))
        ->toThrow(UiDependenciesMissing::class);
});

test('the expiry is a choice list capped by the policy, defaulting to the recommended lifetime', function () {
    bootUi();
    config()->set('mcp-kit.tokens.max_days', 180);
    config()->set('mcp-kit.tokens.default_days', 60);
    $user = acmeUser();
    $this->actingAs($user);

    $component = Livewire::test(TokensPage::class)
        ->assertSet('expiresDays', 60)
        ->assertSee('60 days (recommended)')
        ->assertSee('180 days')
        ->assertDontSee('365 days');

    expect(array_keys($component->instance()->expiryOptions))->toBe([30, 60, 90, 180]);

    Livewire::test(TokensPage::class)->set('name', 'Laptop')->set('preset', 'read')->set('expiresDays', 999)->call('create')->assertStatus(422);

    expect($user->tokens()->count())->toBe(0);

    Livewire::test(TokensPage::class)->set('name', 'Laptop')->set('preset', 'read')->set('expiresDays', 30)->call('create')->assertHasNoErrors();

    expect($user->tokens()->sole()->expires_at->toDateString())->toBe(now()->addDays(30)->toDateString());
});
