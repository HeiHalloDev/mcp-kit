<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Enums\PrincipalKind;
use HeiHallo\McpKit\McpKit;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Principals\DefaultPrincipalResolver;

afterEach(fn () => DefaultPrincipalResolver::blockedUsing(null));

it('resolves a person with staff, privileged and blocked from the checker and the model', function () {
    $resolver = app(PrincipalResolver::class);
    $user = actingWith(acmeUser(['staff', 'things']), ['acme:things:read'], 'laptop');
    $principal = $resolver->resolve($user);

    expect($principal)->toBeInstanceOf(Principal::class)
        ->and($principal->kind)->toBe(PrincipalKind::Person)
        ->and($principal->isPerson())->toBeTrue()
        ->and($principal->name)->toBe('Kari Nordmann')
        ->and($principal->firstName())->toBe('Kari')
        ->and($principal->staff)->toBeTrue()
        ->and($principal->privileged)->toBeFalse()
        ->and($principal->blocked)->toBeFalse()
        ->and($principal->abilities())->toBe(['acme:things:read'])
        ->and($principal->tokenName())->toBe('mcp: laptop')
        ->and($principal->signature())->toBe('Kari Nordmann (mcp: laptop)');

    $blocked = $resolver->resolve(acmeUser(['staff'], 'staff', ['blocked_at' => now()]));

    expect($blocked->blocked)->toBeTrue();
});

it('resolves a service client, blocked when inactive', function () {
    $resolver = app(PrincipalResolver::class);
    $client = ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']);
    $off = ServiceClient::query()->create(['name' => 'Old', 'slug' => 'old', 'is_active' => false]);

    expect($resolver->resolve($client)->kind)->toBe(PrincipalKind::Service)
        ->and($resolver->resolve($client)->isService())->toBeTrue()
        ->and($resolver->resolve($client)->blocked)->toBeFalse()
        ->and($resolver->resolve($client)->staff)->toBeFalse()
        ->and($resolver->resolve($off)->blocked)->toBeTrue()
        ->and($resolver->resolve($client)->name)->toBe('Flex');
});

it('lets an app decide blocked with a closure', function () {
    McpKit::blockedUsing(fn ($user): bool => $user->email === 'out@example.test');

    $resolver = app(PrincipalResolver::class);

    expect($resolver->resolve(acmeUser(attributes: ['email' => 'out@example.test']))->blocked)->toBeTrue()
        ->and($resolver->resolve(acmeUser())->blocked)->toBeFalse();
});

it('lets an app replace the resolver with a closure that may delegate', function () {
    McpKit::resolvePrincipalUsing(function ($tokenable, PrincipalResolver $default): ?Principal {
        $principal = $default->resolve($tokenable);

        return $principal === null ? null : new Principal(
            kind: $principal->kind,
            tokenable: $principal->tokenable,
            name: 'Renamed '.$principal->name,
            email: $principal->email,
            blocked: $principal->blocked,
            staff: $principal->staff,
            privileged: $principal->privileged,
            token: $principal->token,
        );
    });

    expect(app(PrincipalResolver::class)->resolve(acmeUser())->name)->toBe('Renamed Kari Nordmann');
});
