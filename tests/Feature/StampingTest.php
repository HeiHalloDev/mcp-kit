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

    // The suite runs in the console.
    expect($row->channel)->toBe('cli')->and($row->token_name)->toBeNull()->and($row->source)->toBeNull();
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
        ->and(KitActivity::query()->channel(ActivityChannel::Cli)->count())->toBe(2)
        ->and(KitActivity::query()->first()->channel)->toBe(ActivityChannel::Cli);
});

test('the channel enum is rich', function () {
    foreach (ActivityChannel::cases() as $channel) {
        expect($channel->label())->toBeString()->and($channel->icon())->toBeString();
    }
});
