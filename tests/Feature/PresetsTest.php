<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\PresetResolver;

it('filters presets by the person\'s permissions', function () {
    $presets = app(PresetResolver::class);
    $user = acmeUser(['staff', 'things']);

    expect($presets->grantableFor($user))->toBe(['acme:things:read', 'acme:things:write', 'acme:events:write'])
        ->and($presets->abilitiesFor($user, 'read'))->toBe(['acme:things:read'])
        ->and($presets->abilitiesFor($user, 'work'))->toBe(['acme:things:read', 'acme:things:write', 'acme:events:write'])
        ->and($presets->abilitiesFor($user, 'analyst'))->toBe([])
        ->and($presets->abilitiesFor($user, 'full'))->toBe([])
        ->and(array_keys($presets->availableFor($user)))->toBe(['read', 'work'])
        ->and($presets->extrasFor($user))->toBe([]);
});

it('gives privileged owners the wildcards and the extras', function () {
    $presets = app(PresetResolver::class);
    $admin = acmeAdmin();

    expect($presets->abilitiesFor($admin, 'full'))->toBe(['acme:*', 'reports:*'])
        ->and($presets->abilitiesFor($admin, 'analyst'))->toBe(['reports:read'])
        ->and($presets->extrasFor($admin))->toBe(['acme:admin' => 'Change what other people may do'])
        ->and(array_keys($presets->availableFor($admin)))->toBe(['read', 'work', 'full', 'analyst']);
});

it('gives a blocked person nothing', function () {
    $presets = app(PresetResolver::class);
    $blocked = acmeAdmin(['blocked_at' => now()]);

    expect($presets->grantableFor($blocked))->toBe([])
        ->and($presets->abilitiesFor($blocked, 'full'))->toBe([])
        ->and($presets->extrasFor($blocked))->toBe([]);
});

it('labels stored abilities back to a preset', function () {
    $presets = app(PresetResolver::class);

    expect($presets->labelForAbilities([]))->toBe('None')
        ->and($presets->labelForAbilities(['acme:*', 'reports:*']))->toBe('Full')
        ->and($presets->labelForAbilities(['*']))->toBe('Full')
        ->and($presets->labelForAbilities(['reports:read']))->toBe('Analyst')
        ->and($presets->labelForAbilities(['acme:things:read']))->toBe('Read only')
        ->and($presets->labelForAbilities(['acme:things:read', 'acme:things:write']))->toBe('Work')
        ->and($presets->labelForAbilities(['acme:*', 'acme:admin']))->toBe('Full + admin')
        ->and($presets->grantsWrite(['acme:things:read']))->toBeFalse()
        ->and($presets->grantsWrite(['acme:things:write']))->toBeTrue()
        ->and($presets->grantsExplicitOnly(['acme:admin']))->toBeTrue()
        ->and($presets->expiresDaysFor('read'))->toBeNull();
});
