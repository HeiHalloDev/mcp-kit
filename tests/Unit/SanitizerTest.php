<?php

declare(strict_types=1);

use HeiHallo\McpKit\Audit\Sanitizer;

/*
 * Redaction is keyed on the name, at any depth — a nested `password`
 * inside a declared object parameter is the case strict parameters cannot
 * catch, because it only inspects the top level.
 */

test('secret-looking keys are redacted, at the top level and nested', function () {
    $clean = Sanitizer::arguments([
        'query' => 'Widget',
        'password' => 'hunter2',
        'payload' => ['api_key' => 'sk-live-1', 'label' => 'fine'],
    ]);

    expect($clean['query'])->toBe('Widget')
        ->and($clean['password'])->toBe('[REDACTED]')
        ->and($clean['payload']['api_key'])->toBe('[REDACTED]')
        ->and($clean['payload']['label'])->toBe('fine');
});

test('long strings are capped and big arrays counted rather than stored', function () {
    $clean = Sanitizer::arguments([
        'note' => str_repeat('a', 400),
        'ids' => range(1, 80),
    ], maxLength: 200, maxItems: 50);

    expect(strlen($clean['note']))->toBeLessThanOrEqual(201)
        ->and($clean['ids'])->toBe('[array:80]');
});
