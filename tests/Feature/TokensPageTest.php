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
        // The configured default (work), not whatever preset happens to list first.
        ->assertSet('preset', 'work')
        ->set('name', 'Laptop')
        ->set('preset', 'full')
        ->set('extras', ['acme:admin'])
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('shown only once')
        ->assertSee('claude mcp add acme ')
        ->assertSee('claude mcp add acme-reports ')
        ->assertSee('Which model')
        ->assertSee('Sonnet, effort low');

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
        // The choices live in the mint form, which opens on demand.
        ->call('toggleForm')
        ->assertSee('60 days (recommended)')
        ->assertSee('180 days')
        ->assertDontSee('365 days');

    expect(array_keys($component->instance()->expiryOptions))->toBe([30, 60, 90, 180]);

    Livewire::test(TokensPage::class)->set('name', 'Laptop')->set('preset', 'read')->set('expiresDays', 999)->call('create')->assertStatus(422);

    expect($user->tokens()->count())->toBe(0);

    Livewire::test(TokensPage::class)->set('name', 'Laptop')->set('preset', 'read')->set('expiresDays', 30)->call('create')->assertHasNoErrors();

    expect($user->tokens()->sole()->expires_at->toDateString())->toBe(now()->addDays(30)->toDateString());
});

test('the mint form stays behind an Add button and closes again after minting', function () {
    bootUi();
    $this->actingAs(acmeAdmin());

    Livewire::test(TokensPage::class)
        ->assertSet('showForm', false)
        ->assertSee('Add token')
        ->assertDontSee('Name it after where it lives')
        ->call('toggleForm')
        ->assertSet('showForm', true)
        ->assertSee('Name it after where it lives')
        ->set('name', 'Laptop')
        ->call('create')
        ->assertHasNoErrors()
        // Closing on success puts the fresh token, not an empty form, in front
        // of the person who just minted it.
        ->assertSet('showForm', false)
        ->assertSee('shown only once');
});

test('cancelling the form forgets what was typed', function () {
    bootUi();
    $this->actingAs(acmeAdmin());

    Livewire::test(TokensPage::class)
        ->call('toggleForm')
        ->set('name', 'half typed')
        ->call('create')
        ->call('toggleForm')
        ->assertSet('name', '')
        ->assertHasNoErrors();
});

test('the connect snippets offer every server at once, one at a time, and a way out', function () {
    bootUi();
    $this->actingAs(acmeAdmin());

    Livewire::test(TokensPage::class)
        ->call('toggleForm')
        ->set('name', 'Laptop')
        ->set('preset', 'full')
        ->call('create')
        // All servers in one block, and each on its own so a person can add
        // just the one they need.
        ->assertSee('One server at a time')
        ->assertSee('claude mcp add acme ')
        ->assertSee('claude mcp add acme-reports ')
        // The Codex tab leads with the ChatGPT app's own form (the app
        // never sees shell env vars) and falls back to one terminal paste
        // that writes the config.toml blocks. The form takes one value per
        // field, so every value gets its own copy button.
        ->assertSeeInOrder(['Name', 'acme', 'Type', 'Streamable HTTP', 'URL', 'Header key', 'Authorization', 'Header value', 'Bearer '])
        ->assertSee('Copy Header value')
        ->assertSee('cat >> ~/.codex/config.toml')
        ->assertSee('[mcp_servers.acme]')
        ->assertSee('[mcp_servers.acme-reports]')
        ->assertSee('http_headers = { Authorization = ', false)
        ->assertDontSee('bearer_token_env_var')
        ->assertDontSee('bearer-token-env-var')
        // Codex is told about working_on and report_gap in the file it
        // reads at the start of every thread.
        ->assertSee('cat >> ~/.codex/AGENTS.md')
        ->assertSee('<!-- mcp-kit -->')
        ->assertSee('call `report_gap` before working around it', false)
        // Removal is by name and carries no token.
        ->assertSee('claude mcp remove acme')
        ->assertSee('codex mcp remove acme')
        ->assertSee('Remove a connection');
});

test('the default radio honours tokens.default_preset instead of listing order', function () {
    config()->set('mcp-kit.tokens.default_preset', 'work');

    $user = acmeUser(['staff', 'things']);
    $this->actingAs($user);

    // 'read' sorts first in the resolved presets; the page must still open on 'work'.
    Livewire::test(TokensPage::class)->assertSet('preset', 'work');
});

test('the default falls back to the first preset the person can actually mint', function () {
    config()->set('mcp-kit.tokens.default_preset', 'work');

    // Only `reports`: work resolves to reports:read, but suppose the default
    // pointed at a preset this person cannot mint — the page must not open on
    // an empty radio.
    config()->set('mcp-kit.tokens.default_preset', 'no_such_preset');

    $user = acmeUser(['staff', 'things']);
    $this->actingAs($user);

    $component = Livewire::test(TokensPage::class);

    expect($component->get('preset'))->not->toBe('')
        ->and($component->get('preset'))->not->toBe('no_such_preset');
});
