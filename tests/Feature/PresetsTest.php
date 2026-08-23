<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Presets\ConfigPresetResolver;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeReportsServer;

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

it('keeps a server that opted out of presets off the staff page and can require staff', function () {
    config()->set('mcp-kit.servers.customer', [
        'class' => AcmeReportsServer::class,
        'path' => '/mcp/customer',
        'wildcard' => 'customer:*',
        'requires_staff' => false,
        'presets' => false,
    ]);
    config()->set('mcp-kit.catalogue.abilities.customer:team:read', ['See your team', null, 'customer']);
    $presets = app()->make(ConfigPresetResolver::class);
    $admin = acmeAdmin();

    expect($presets->grantableFor($admin))->not->toContain('customer:team:read')
        ->and($presets->abilitiesFor($admin, 'full'))->toBe(['acme:*', 'reports:*']);

    config()->set('mcp-kit.tokens.staff_only', true);

    expect($presets->grantableFor(acmeUser(['things'])))->toBe([])
        ->and($presets->grantableFor(acmeUser(['staff', 'things'])))->not->toBe([]);
});
