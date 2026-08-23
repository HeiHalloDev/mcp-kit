<?php

declare(strict_types=1);

use Spatie\Activitylog\Models\Activity;

test('mcp-kit:prune deletes old mcp rows and nothing else', function () {
    Activity::query()->insert([
        ['log_name' => 'mcp', 'description' => 'old', 'created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100)],
        ['log_name' => 'mcp', 'description' => 'recent', 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)],
        ['log_name' => 'things', 'description' => 'old domain', 'created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100)],
    ]);

    $this->artisan('mcp-kit:prune')->expectsOutputToContain('Pruned 1')->assertSuccessful();

    expect(Activity::query()->pluck('description')->all())->toBe(['recent', 'old domain']);

    $this->artisan('mcp-kit:prune', ['--days' => 5])->expectsOutputToContain('Pruned 1')->assertSuccessful();

    expect(Activity::query()->where('log_name', 'mcp')->count())->toBe(0);
});
