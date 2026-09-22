<?php

declare(strict_types=1);

use HeiHallo\McpKit\Testing\Mcp;
use Illuminate\Support\Facades\Cache;

/**
 * The same tool over and over, when one call would have done it. A
 * curriculum sweep spent 434 calls reading a study a page at a time before
 * anybody noticed the tool that reads the whole branch.
 */
beforeEach(function () {
    Cache::flush();

    config()->set('mcp-kit.hints', [
        'enabled' => true,
        'after' => 3,
        'repeat_every' => 2,
        'instead_of' => [
            'list_things' => ['use' => 'report', 'say' => 'answers the whole question in one call'],
        ],
    ]);
});

function callListThings(string $token): string
{
    return (string) Mcp::call($token, '/mcp/acme', 'list_things', ['query' => 'Wid'])->getContent();
}

it('says nothing for the first few calls, then names the shorter road', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    expect(callListThings($token))->not->toContain('shorter road')
        ->and(callListThings($token))->not->toContain('shorter road');

    $third = callListThings($token);

    expect($third)->toContain('3 calls to list_things')
        ->and($third)->toContain('`report`')
        ->and($third)->toContain('answers the whole question in one call')
        ->and($third)->toContain('Nothing is wrong with the call you just made');
});

it('says it again while the assistant keeps going, not on every call', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);
    $said = [];

    foreach (range(1, 7) as $call) {
        if (str_contains(callListThings($token), 'shorter road')) {
            $said[] = $call;
        }
    }

    expect($said)->toBe([3, 5, 7]);
});

it('counts per person, never across them', function () {
    $mine = acmeToken(acmeUser(), ['acme:things:read'], 'mine');
    $theirs = acmeToken(acmeUser(), ['acme:things:read'], 'theirs');

    callListThings($mine);
    callListThings($mine);

    expect(callListThings($theirs))->not->toContain('shorter road')
        ->and(callListThings($mine))->toContain('3 calls to list_things');
});

it('stays quiet for a tool nobody named a shorter road for, and when hints are off', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    config()->set('mcp-kit.hints.instead_of', []);

    foreach (range(1, 4) as $ignored) {
        expect(callListThings($token))->not->toContain('shorter road');
    }

    config()->set('mcp-kit.hints.instead_of', ['list_things' => ['use' => 'report', 'say' => 'answers it in one call']]);
    config()->set('mcp-kit.hints.enabled', false);

    foreach (range(1, 4) as $ignored) {
        expect(callListThings($token))->not->toContain('shorter road');
    }
});
