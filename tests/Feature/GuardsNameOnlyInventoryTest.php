<?php

declare(strict_types=1);

use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Testing\Guards;

/*
 * The inventory guard against a snapshot pinned before parameters were in
 * it. An app on an older snapshot is not failing a guard it never agreed
 * to; it opts in when it runs mcp:inventory.
 */

beforeEach(function () {
    file_put_contents(__DIR__.'/../tmp/tool-inventory-names.json', json_encode(app(ToolReference::class)->inventory()));
});

Guards::all(inventory: __DIR__.'/../tmp/tool-inventory-names.json', only: ['inventory']);
