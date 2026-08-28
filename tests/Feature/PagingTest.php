<?php

declare(strict_types=1);

use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;

/*
 * A sweep of six apps found 62 list tools and five that let you past the
 * cap. The rest ordered one fixed way with no offset, so anything beyond
 * the limit was unreachable by any route — and a full page looked exactly
 * like a complete answer.
 */

function things(string $token, array $arguments = []): array
{
    $body = Mcp::call($token, '/mcp/acme', 'list_things', $arguments)->json('result.content.0.text');

    return json_decode((string) $body, true) ?? [];
}

test('the reply says how many matched, not just how many came back', function () {
    foreach (['Alpha', 'Bravo', 'Charlie', 'Delta'] as $name) {
        Thing::query()->create(['name' => $name]);
    }

    $page = things(acmeToken(acmeUser(), ['acme:things:read']), ['limit' => 2]);

    expect($page['total'])->toBe(4)
        ->and($page['limit'])->toBe(2)
        ->and($page['has_more'])->toBeTrue()
        ->and($page['things'])->toHaveCount(2);
});

test('offset reaches rows the limit hid', function () {
    foreach (['Alpha', 'Bravo', 'Charlie', 'Delta'] as $name) {
        Thing::query()->create(['name' => $name]);
    }

    $token = acmeToken(acmeUser(), ['acme:things:read']);

    $first = things($token, ['limit' => 2]);
    $second = things($token, ['limit' => 2, 'offset' => 2]);

    expect(array_column($first['things'], 'name'))->toBe(['Alpha', 'Bravo'])
        ->and(array_column($second['things'], 'name'))->toBe(['Charlie', 'Delta'])
        ->and($second['has_more'])->toBeFalse();
});

test('direction reverses the order without the tool knowing how', function () {
    foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
        Thing::query()->create(['name' => $name]);
    }

    $page = things(acmeToken(acmeUser(), ['acme:things:read']), ['direction' => 'desc', 'limit' => 1]);

    expect($page['things'][0]['name'])->toBe('Charlie');
});

test("the tool's own order is kept when nobody asks for one", function () {
    foreach (['Charlie', 'Alpha', 'Bravo'] as $name) {
        Thing::query()->create(['name' => $name]);
    }

    $page = things(acmeToken(acmeUser(), ['acme:things:read']));

    expect(array_column($page['things'], 'name'))->toBe(['Alpha', 'Bravo', 'Charlie']);
});

test('an unknown sort falls back rather than throwing', function () {
    Thing::query()->create(['name' => 'Alpha']);

    $page = things(acmeToken(acmeUser(), ['acme:things:read']), ['sort' => 'name']);

    expect($page['total'])->toBe(1);
});
