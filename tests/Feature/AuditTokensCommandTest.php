<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('mcp:audit-tokens lists stale, expired, never-expiring, alias and orphaned tokens and can revoke the orphans', function () {
    $fine = acmeUser();
    $fine->createToken('mcp: fine', ['acme:things:read'], now()->addDays(30));
    $fine->tokens()->update(['last_used_at' => now()]);

    $stale = acmeUser(attributes: ['email' => 'stale@example.test', 'name' => 'Stale Person']);
    $stale->createToken('mcp: old laptop', ['acme:things:read'], now()->subDay());
    $stale->tokens()->update(['last_used_at' => now()->subDays(200), 'created_at' => now()->subDays(200)]);

    $alias = acmeUser(attributes: ['email' => 'alias@example.test', 'name' => 'Alias Person']);
    $alias->createToken('mcp: phone', ['old:things:read'], now()->addDays(30));

    $lost = acmeUser(['staff'], 'staff', ['email' => 'lost@example.test', 'name' => 'Lost Permission']);
    $lost->createToken('mcp: desk', ['acme:things:read'], now()->addDays(30));

    $blocked = acmeUser(['staff', 'things'], 'staff', ['email' => 'blocked@example.test', 'name' => 'Blocked Person', 'blocked_at' => now()]);
    $blocked->createToken('mcp: desk', ['acme:things:read']);

    // Table output bypasses expectsOutputToContain; read the buffer instead.
    expect(Artisan::call('mcp:audit-tokens'))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain('Stale Person')
        ->toContain('expired')
        ->toContain('unused for 90+ days')
        ->toContain('legacy alias old:things:read')
        ->toContain('refused: owner lacks things')
        ->toContain('owner blocked')
        ->toContain('never expires')
        ->toContain('Add --revoke-orphaned')
        ->not->toContain('mcp: fine');

    expect(Artisan::call('mcp:audit-tokens', ['--revoke-orphaned' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('Revoked 2 orphaned token(s)');

    expect($fine->tokens()->count())->toBe(1)
        ->and($stale->tokens()->count())->toBe(1)
        ->and($lost->tokens()->count())->toBe(0)
        ->and($blocked->tokens()->count())->toBe(0);
});

test('mcp:audit-tokens is quiet when everything is fine', function () {
    $user = acmeUser();
    $user->createToken('mcp: fine', ['acme:things:read'], now()->addDays(30));
    $user->tokens()->update(['last_used_at' => now()]);

    $this->artisan('mcp:audit-tokens')->expectsOutputToContain('Nothing to report')->assertSuccessful();
});
