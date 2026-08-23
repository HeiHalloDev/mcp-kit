<?php

declare(strict_types=1);

use HeiHallo\McpKit\McpKitServiceProvider;
use Illuminate\Support\ServiceProvider;

it('exposes exactly two publish tags: config and views', function () {
    $ours = array_keys(ServiceProvider::$publishes[McpKitServiceProvider::class] ?? []);

    $tags = array_keys(array_filter(
        ServiceProvider::$publishGroups,
        fn (array $paths): bool => array_intersect(array_keys($paths), $ours) !== [],
    ));

    sort($tags);

    expect($tags)->toBe(['mcp-kit-config', 'mcp-kit-views'])
        ->and(ServiceProvider::pathsToPublish(McpKitServiceProvider::class, 'mcp-kit-config'))->toHaveCount(1);
});
