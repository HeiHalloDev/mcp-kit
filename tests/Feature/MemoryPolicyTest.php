<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\MemoryPolicy;
use HeiHallo\McpKit\Contracts\MemoryStore;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Memory\AssistantMemory;
use HeiHallo\McpKit\Memory\MemoryLimits;
use HeiHallo\McpKit\Memory\MemoryMerger;
use HeiHallo\McpKit\Memory\SecretDetector;
use HeiHallo\McpKit\Models\ServiceClient;

test('the default policy: self always, others only when privileged, service clients never', function () {
    $policy = app(MemoryPolicy::class);
    $resolver = app(PrincipalResolver::class);
    $user = acmeUser();
    $other = acmeUser(attributes: ['email' => 'o@example.test']);
    $admin = acmeAdmin();
    $client = ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']);

    expect($policy->view($resolver->resolve($user), $user))->toBeTrue()
        ->and($policy->update($resolver->resolve($user), $other))->toBeFalse()
        ->and($policy->update($resolver->resolve($admin), $other))->toBeTrue()
        ->and($policy->view($resolver->resolve($client), $user))->toBeFalse()
        ->and($policy->view($resolver->resolve(acmeAdmin(['blocked_at' => now()])), $other))->toBeFalse();
});

test('the column store round-trips and clears blank memory to null', function () {
    $store = app(MemoryStore::class);
    $user = acmeUser();

    expect($store->get($user)->isEmpty())->toBeTrue();

    $store->put($user, AssistantMemory::fromArray(['role' => 'Support', 'preferences' => ['language' => 'nb']]));

    expect($store->get($user)->role)->toBe('Support')
        ->and($user->fresh()->getAttribute('assistant_memory'))->not->toBeNull();

    $store->put($user, AssistantMemory::empty());

    expect($user->fresh()->getAttribute('assistant_memory'))->toBeNull();
});

test('the merger validates lengths, onboarding values and note indexes', function () {
    $merger = new MemoryMerger(new MemoryLimits(itemChars: 10, roleChars: 5));
    $empty = AssistantMemory::empty();

    expect(fn () => $merger->merge($empty, ['role' => 'too long role'], false, null))->toThrow(InvalidArgumentException::class, 'role is too long')
        ->and(fn () => $merger->merge($empty, ['routines' => ['way too long item']], false, null))->toThrow(InvalidArgumentException::class, 'routines is too long')
        ->and(fn () => $merger->merge($empty, ['onboarding' => 'maybe'], false, null))->toThrow(InvalidArgumentException::class, 'onboarding must be');

    $merged = $merger->merge($empty, ['note' => 'one', 'onboarding' => 'declined'], false, null);
    $merged = $merger->merge($merged, ['note' => 'two'], false, null);
    $merged = $merger->merge($merged, ['forget' => ['note:1']], false, null);

    expect(array_column($merged->notes, 'text'))->toBe(['two'])
        ->and($merged->onboardingDeclined())->toBeTrue()
        ->and($merger->merge($merged, ['onboarding' => 'reset'], false, null)->onboarding)->toBe([])
        ->and($merged->version)->toBe(1);
});

/**
 * Talking about a credential is not carrying one. The first rules matched
 * the word alone, so a report about the bearer-token upload path — which
 * contained no token — was refused, and so was every gap about resetting a
 * password.
 */
test('it tells a credential from a sentence about one', function () {
    $secret = [
        'I had to log in with password hunter2 instead.',
        'passord: korrekthest42',
        'Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ012345',
        'api_key=ab12cd34ef56gh',
        'secret is correcthorsebatterystaple',
        '-----BEGIN RSA PRIVATE KEY-----',
    ];

    $prose = [
        'the bearer token path is unusable for connector staff',
        'no way to reset a student password from here',
        'a gap about passwords and tokens',
        'the token sits in the connector settings, so the tool cannot send it',
    ];

    foreach ($secret as $text) {
        expect(SecretDetector::looksSecret($text))->toBeTrue("should refuse: {$text}");
    }

    foreach ($prose as $text) {
        expect(SecretDetector::looksSecret($text))->toBeFalse("should allow: {$text}");
    }
});

test('a field named for a credential still catches one, and lets prose through', function () {
    expect(SecretDetector::looksSecret(['api_token' => 'aBcDeFgH12345678']))->toBeTrue()
        ->and(SecretDetector::looksSecret(['result' => 'the bearer token path is unusable']))->toBeFalse()
        ->and(SecretDetector::looksSecret(['token' => 'the one in the settings']))->toBeFalse();
});
