<?php

declare(strict_types=1);

use HeiHallo\McpKit\Events\TokenMinted;
use HeiHallo\McpKit\Events\TokenRevoked;
use HeiHallo\McpKit\Exceptions\TokenRefused;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Tokens\TokenMinter;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

test('mcp:token mints with the default preset, a 90-day expiry and one connect line per server', function () {
    Event::fake([TokenMinted::class]);
    $user = acmeAdmin();

    $this->artisan('mcp:token', ['email' => $user->email, '--name' => 'laptop'])
        ->expectsOutputToContain('Token created for Ada Admin (mcp: laptop)')
        ->expectsOutputToContain('Abilities: acme:things:read, acme:things:write, acme:events:write, reports:read')
        ->expectsOutputToContain('Expires: '.now()->addDays(90)->toDateString())
        ->expectsOutputToContain('claude mcp add acme --scope user --transport http http://localhost/mcp/acme')
        ->expectsOutputToContain('claude mcp add acme-reports --scope user --transport http http://localhost/mcp/reports')
        ->assertSuccessful();

    $token = $user->tokens()->sole();

    expect($token->name)->toBe('mcp: laptop')
        ->and($token->expires_at->toDateString())->toBe(now()->addDays(90)->toDateString());

    Event::assertDispatched(TokenMinted::class);

    $row = Activity::query()->where('log_name', 'mcp')->sole();

    expect($row->event)->toBe('token_minted')->and($row->properties['preset'])->toBe('work');
});

test('mcp:token caps the expiry, accepts explicit abilities and refuses what the owner may not hold', function () {
    $user = acmeUser(['staff', 'things']);

    $this->artisan('mcp:token', ['email' => $user->email, '--abilities' => 'acme:things:read', '--expires' => 1000])->assertSuccessful();

    expect($user->tokens()->sole()->expires_at->toDateString())->toBe(now()->addDays(365)->toDateString());

    $this->artisan('mcp:token', ['email' => $user->email, '--abilities' => 'acme:*'])
        ->expectsOutputToContain('can only be held by someone with an Admin role')
        ->assertFailed();

    $this->artisan('mcp:token', ['email' => $user->email, '--abilities' => 'reports:read'])
        ->expectsOutputToContain("lacks the 'reports' permission")
        ->assertFailed();

    $this->artisan('mcp:token', ['email' => $user->email, '--abilities' => 'old:things:read'])
        ->expectsOutputToContain('legacy alias')
        ->assertFailed();

    $this->artisan('mcp:token', ['email' => $user->email, '--abilities' => 'acme:nope'])
        ->expectsOutputToContain("Unknown ability 'acme:nope'")
        ->assertFailed();

    $this->artisan('mcp:token', ['email' => $user->email, '--preset' => 'full'])
        ->expectsOutputToContain('resolves to nothing')
        ->assertFailed();

    $this->artisan('mcp:token', ['email' => $user->email, '--preset' => 'x', '--abilities' => 'y'])->assertFailed();
    $this->artisan('mcp:token', ['email' => 'nobody@example.test'])->assertFailed();
});

test('mcp:token --revoke removes only kit tokens and fires the event', function () {
    Event::fake([TokenRevoked::class]);
    $user = acmeUser();
    $user->createToken('mcp: one', ['acme:things:read']);
    $user->createToken('mcp: two', ['acme:things:read']);
    $user->createToken('calendar', ['calendar']);

    $this->artisan('mcp:token', ['email' => $user->email, '--revoke' => true])
        ->expectsOutputToContain('Revoked 2 MCP token(s)')
        ->assertSuccessful();

    expect($user->tokens()->pluck('name')->all())->toBe(['calendar']);

    Event::assertDispatchedTimes(TokenRevoked::class, 2);
});

test('the minter refuses blocked people and service clients', function () {
    $minter = app(TokenMinter::class);

    expect(fn () => $minter->mint(acmeUser(['staff'], 'staff', ['blocked_at' => now()]), 'x', ['acme:things:read']))
        ->toThrow(TokenRefused::class, 'blocked');

    $client = ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']);

    expect(fn () => $minter->mint($client, 'x', ['acme:things:read']))->toThrow(TokenRefused::class, 'mcp:client-token');
});
