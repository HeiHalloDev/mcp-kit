<?php

declare(strict_types=1);

use HeiHallo\McpKit\Activity\Activity as KitActivity;
use HeiHallo\McpKit\Enums\ActivityChannel;
use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use Spatie\Activitylog\Models\Activity;

test('rows written outside a call are stamped by surface', function () {
    $thing = Thing::query()->create(['name' => 'Widget']);

    activity('things')->performedOn($thing)->log('created');

    $row = Activity::query()->where('log_name', 'things')->sole();

    // Tests count as unattended: no user, no artisan command → system.
    expect($row->channel)->toBe('system')->and($row->token_name)->toBeNull()->and($row->source)->toBeNull();
});

test('a default source is stamped when configured and never overwrites an explicit one', function () {
    config()->set('mcp-kit.activity.default_source', 'acme');
    $thing = Thing::query()->create(['name' => 'Widget']);

    activity('things')->performedOn($thing)->log('one');
    activity('things')->performedOn($thing)->tap(fn ($a) => $a->source = 'other')->log('two');

    expect(Activity::query()->where('description', 'one')->sole()->source)->toBe('acme')
        ->and(Activity::query()->where('description', 'two')->sole()->source)->toBe('other');
});

test('the package model is used when the app left the plain spatie model, with scopes and casts', function () {
    expect(config('activitylog.activity_model'))->toBe(KitActivity::class);

    $thing = Thing::query()->create(['name' => 'Widget']);
    activity('things')->performedOn($thing)->log('created');
    activity('mcp')->log('call');

    expect(KitActivity::query()->mcp()->count())->toBe(1)
        ->and(KitActivity::query()->channel(ActivityChannel::System)->count())->toBe(2)
        ->and(KitActivity::query()->first()->channel)->toBe(ActivityChannel::System);
});

test('the channel enum is rich', function () {
    foreach (ActivityChannel::cases() as $channel) {
        expect($channel->label())->toBeString()->and($channel->icon())->toBeString()->and($channel->color())->toBeString();
    }

    expect(ActivityChannel::options())->toHaveKey('mcp');
});

test('a signed-in user stamps web, a personal token outside a call stamps api', function () {
    $user = acmeUser();
    $this->actingAs($user);
    activity('things')->log('web change');

    $this->actingAs(actingWith(acmeUser(attributes: ['email' => 'api@example.test']), ['acme:things:read']));
    activity('things')->log('api change');

    expect(Activity::query()->where('description', 'web change')->sole()->channel)->toBe('web')
        ->and(Activity::query()->where('description', 'api change')->sole()->channel)->toBe('api');
});
