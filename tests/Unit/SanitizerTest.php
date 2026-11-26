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

    expect($clean['note'])->toBe(str_repeat('a', 200).'...')
        ->and($clean['ids'])->toBe('[array:80]');
});

/*
 * A Norwegian national id number (fødselsnummer) is eleven digits. Tools
 * take it as an argument (Flex's update_student takes `ssn`), and the audit
 * trail stores arguments — so the number must never reach it.
 */
function elevenDigits(): string
{
    return str_repeat('0', 6).str_repeat('9', 5);
}

test('national id numbers are redacted under every name they go by', function (string $key) {
    $clean = Sanitizer::arguments([$key => elevenDigits(), 'name' => 'Kari']);

    expect($clean[$key])->toBe('[REDACTED]')
        ->and($clean['name'])->toBe('Kari');
})->with(['ssn', 'SSN', 'personnummer', 'fodselsnummer', 'fødselsnummer', 'Fødselsnummer', 'national_id']);

test('a national id number nested in an object is redacted too', function () {
    $clean = Sanitizer::arguments(['student' => ['ssn' => elevenDigits(), 'email' => 'kari@example.test']]);

    expect($clean['student']['ssn'])->toBe('[REDACTED]')
        ->and($clean['student']['email'])->toBe('kari@example.test');
});

test('an app can add its own keys to redact, on top of the built-in ones', function () {
    config(['mcp-kit.redact_keys' => ['Birth_Number']]);

    $clean = Sanitizer::arguments(['birth_number' => elevenDigits(), 'ssn' => elevenDigits(), 'note' => 'keep']);

    expect($clean['birth_number'])->toBe('[REDACTED]')
        ->and($clean['ssn'])->toBe('[REDACTED]')
        ->and($clean['note'])->toBe('keep');
});

test('keys that only contain a redacted name are kept', function () {
    $clean = Sanitizer::arguments(['ssn_on_file' => true, 'has_national_id' => false]);

    expect($clean)->toBe(['ssn_on_file' => true, 'has_national_id' => false]);
});
