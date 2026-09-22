<?php

declare(strict_types=1);

use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Testing\Guards;

/*
 * The app-facing one-liner, run against the Acme fixtures: what every app
 * gets from `Guards::all(...)`. The docs page and inventory are written
 * first so docsCurrent and inventory have something to compare against.
 */

beforeEach(function () {
    $docs = config('mcp-kit.docs.path');
    $inventory = config('mcp-kit.docs.inventory');

    file_put_contents($docs, "---\nnav_title: Tools\n---\n\n<!-- generated:tools:start -->\n<!-- generated:tools:end -->\n");
    $this->artisan('mcp:docs')->run();
    file_put_contents($inventory, json_encode(app(ToolReference::class)->surface()));
});

Guards::all(inventory: __DIR__.'/../tmp/tool-inventory.json');
