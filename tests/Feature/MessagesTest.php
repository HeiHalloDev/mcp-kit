<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Messages\Messages;
use HeiHallo\McpKit\Models\Message;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\ListThingsTool;
use HeiHallo\McpKit\Tools\ToolAppearances;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Messages reach a person through their own assistant: on top of the
 * result of their next tool call, until the assistant confirms it told
 * them. An answer left in whoami counted as heard the moment it was read,
 * and a staff member learned in a meeting about tools built for him weeks
 * before.
 */

function firstBlock(string $token, string $tool = 'list_things', array $arguments = ['query' => 'Wid']): string
{
    return (string) Mcp::call($token, '/mcp/acme', $tool, $arguments)->json('result.content.0.text');
}

function confirmSend(string $token, array $arguments): array
{
    return Mcp::call($token, '/mcp/acme', 'send_message', $arguments + ['confirm' => true])->json('result');
}

it('lets a colleague message another, first on their next tool result, until their assistant confirms', function () {
    $kari = acmeUser(attributes: ['name' => 'Kari Kollega', 'email' => 'kari@example.com']);
    $ola = acmeUser(attributes: ['name' => 'Ola Other']);
    $sender = acmeToken(acmeUser(attributes: ['name' => 'Siri Sender']), ['acme:things:read']);
    $karis = acmeToken($kari, ['acme:things:read']);
    $olas = acmeToken($ola, ['acme:things:read']);

    $preview = Mcp::call($sender, '/mcp/acme', 'send_message', ['to' => ['kari@example.com'], 'body' => 'Kurset er klart for gjennomgang.'])->json('result.content.0.text');
    expect($preview)->toContain('Kari Kollega')->and(Message::query()->count())->toBe(0);

    confirmSend($sender, ['to' => ['kari@example.com'], 'body' => 'Kurset er klart for gjennomgang.']);
    $id = Message::query()->sole()->key();

    $first = firstBlock($karis);
    expect($first)->toContain('MESSAGE FOR Kari Kollega')
        ->and($first)->toContain('From Siri Sender: Kurset er klart for gjennomgang.')
        ->and($first)->toContain("[{$id}]")
        ->and(firstBlock($karis))->toContain('MESSAGE FOR')
        ->and(firstBlock($olas))->not->toContain('MESSAGE FOR');

    Mcp::call($karis, '/mcp/acme', 'message_delivered', ['ids' => [$id]])->assertSuccessful();

    expect(firstBlock($karis))->not->toContain('MESSAGE FOR');

    $sent = Mcp::call($sender, '/mcp/acme', 'list_messages', [])->json('result.content.0.text');
    expect($sent)->toContain('"status":"delivered"')->and($sent)->toContain('Kari Kollega');
});

it('stops after max_shows when nobody confirms, and shows at most per_call at once', function () {
    config()->set('mcp-kit.messages.max_shows', 2);
    config()->set('mcp-kit.messages.per_call', 1);
    $kari = acmeUser(attributes: ['name' => 'Kari Kollega']);
    $token = acmeToken($kari, ['acme:things:read']);

    foreach (['Først', 'Så'] as $body) {
        app(Messages::class)->send($body, ['Kari Kollega'], null, false, null, 'Ada Admin');
    }

    $seen = array_map(fn () => firstBlock($token), range(1, 5));

    expect(collect($seen)->filter(fn ($t) => str_contains($t, 'Først'))->count())->toBe(2)
        ->and(collect($seen)->filter(fn ($t) => str_contains($t, 'Så'))->count())->toBe(2)
        ->and($seen[4])->not->toContain('MESSAGE FOR');
});

it('keeps everyone and an ability for Owner and Dev, and refuses names it cannot place', function () {
    $staff = acmeToken(acmeUser(attributes: ['name' => 'Siri Sender']), ['acme:things:read']);
    $admin = acmeToken(acmeAdmin(), ['acme:*']);
    $reader = acmeToken(acmeUser(attributes: ['name' => 'Rita Reader']), ['acme:things:read']);
    $writer = acmeToken(acmeUser(attributes: ['name' => 'Wenche Writer']), ['acme:things:read', 'acme:things:write']);

    expect(confirmSend($staff, ['everyone' => true, 'body' => 'Hei alle'])['isError'] ?? false)->toBeTrue()
        ->and(confirmSend($staff, ['to' => ['Ingen Slik'], 'body' => 'Hei'])['isError'] ?? false)->toBeTrue()
        ->and(Message::query()->count())->toBe(0);

    confirmSend($admin, ['ability' => 'acme:things:write', 'body' => 'Nytt skriveverktøy']);
    confirmSend($admin, ['everyone' => true, 'body' => 'Koble til på nytt']);

    expect(firstBlock($writer))->toContain('Nytt skriveverktøy')->toContain('Koble til på nytt')
        ->and(firstBlock($reader))->not->toContain('Nytt skriveverktøy')->toContain('Koble til på nytt');
});

it('brings the answer to a reported gap the same way, and only counts it heard once delivered', function () {
    $kari = acmeUser(attributes: ['name' => 'Kari Kollega']);
    $token = acmeToken($kari, ['acme:things:read']);
    $store = app(GapStore::class);
    $gap = $store->put(new Gap(key: 'export', title: 'cannot export things', need: 'Export', missing: 'An export', reporters: [['name' => 'Kari Kollega', 'at' => now()->toIso8601String()]]));
    $store->put($gap->settled(Gap::DONE, 'Bygget: export_things.', 'Ada Admin'));

    $whoami = (string) Mcp::call($token, '/mcp/acme', 'whoami', [])->json('result.content.1.text');
    expect($whoami)->toContain('Messages for Kari');

    $block = firstBlock($token);
    expect($block)->toContain('cannot export things')->toContain('Bygget: export_things.');

    preg_match('/\[(gap:[^\]]+)\]/', $block, $m);
    Mcp::call($token, '/mcp/acme', 'message_delivered', ['ids' => [$m[1]]])->assertSuccessful();

    expect(firstBlock($token))->not->toContain('MESSAGE FOR')
        ->and($store->settledFor('Kari Kollega'))->toBe([]);
});

it('tells a sign-in connection about tools that arrived after it connected, never a pasted token', function () {
    config()->set('mcp-kit.oauth.enabled', true);
    $kari = acmeUser(attributes: ['name' => 'Kari Kollega']);
    $signedIn = acmeToken($kari, ['acme:things:read'], 'claude');
    // A token a sign-in issued carries the OAuth prefix, the way AuthorizationServer names it.
    $kari->tokens()->latest('id')->first()->forceFill(['name' => 'oauth: Claude'])->save();
    $pasted = acmeToken($kari, ['acme:things:read'], 'laptop');

    Mcp::listTools($signedIn, '/mcp/acme')->assertSuccessful();
    DB::table(ToolAppearances::TABLE)->where('class', ListThingsTool::class)->update(['first_seen_at' => now()->addMinute()]);

    $this->travel(2)->minutes();

    $block = firstBlock($signedIn);
    expect($block)->toContain('MESSAGE FOR')->toContain('`list_things`')->toContain('connect it again')
        ->and(firstBlock($pasted))->not->toContain('MESSAGE FOR');

    // The reminder belongs to this connection's server, and confirming it works.
    preg_match('/\[(reconnect:[^\]]+)\]/', $block, $m);
    expect(Mcp::call($signedIn, '/mcp/acme', 'message_delivered', ['ids' => [$m[1]]])->json('result.content.0.text'))->toContain('"delivered":["'.$m[1].'"]')
        ->and(firstBlock($signedIn))->not->toContain('MESSAGE FOR');
});

it('tells a pasted token about new tools it was made too early to reach, and not the tokens that reach them', function () {
    $kari = acmeUser(attributes: ['name' => 'Kari Kollega']);
    $old = acmeToken($kari, ['acme:things:write'], 'old laptop');
    $full = acmeToken($kari, ['acme:things:read', 'acme:things:write'], 'new laptop');
    $narrow = acmeToken(acmeUser(['staff'], attributes: ['name' => 'Nils Nobody']), ['acme:things:write']);

    Mcp::listTools($full, '/mcp/acme')->assertSuccessful();
    DB::table(ToolAppearances::TABLE)->where('class', ListThingsTool::class)->update(['first_seen_at' => now()->addMinute()]);

    $this->travel(2)->minutes();

    expect(firstBlock($old, 'whoami', []))->toContain('MESSAGE FOR')->toContain('`list_things`')->toContain('make a new token')
        ->and(firstBlock($full))->not->toContain('MESSAGE FOR')
        ->and(firstBlock($narrow, 'whoami', []))->not->toContain('make a new token');
});

it('leaves whoami and tool calls working in an app that has not migrated the message tables', function () {
    Schema::drop('mcp_message_receipts');
    Schema::drop('mcp_messages');
    $token = acmeToken(acmeUser(attributes: ['name' => 'Kari Kollega']), ['acme:things:read']);

    expect((string) Mcp::call($token, '/mcp/acme', 'whoami', [])->json('result.content.0.text'))->toContain('Kari Kollega')
        ->and(firstBlock($token))->not->toContain('MESSAGE FOR');
});

it('keeps a message to a named person until it reaches them, while a broadcast goes stale', function () {
    $sender = acmeToken(acmeAdmin(), ['acme:*']);
    $kari = acmeToken(acmeUser(attributes: ['name' => 'Kari Kollega']), ['acme:things:read']);

    confirmSend($sender, ['to' => ['Kari Kollega'], 'body' => 'Når du er tilbake: kurset er klart.']);
    confirmSend($sender, ['everyone' => true, 'body' => 'Koble til på nytt denne uken.']);

    expect(Message::query()->where('to_everyone', false)->value('expires_at'))->toBeNull();

    $this->travel(40)->days();

    expect(firstBlock($kari))->toContain('kurset er klart')->not->toContain('Koble til på nytt');
});

it('reaches only people who use the tools, by first name or part of a name, and refuses an ambiguous one', function () {
    $sender = acmeToken(acmeUser(attributes: ['name' => 'Siri Sender']), ['acme:things:read']);
    acmeToken(acmeUser(attributes: ['name' => 'Benjamin Christensen']), ['acme:things:read']);
    acmeToken(acmeUser(attributes: ['name' => 'Kari Nordmann']), ['acme:things:read']);
    acmeToken(acmeUser(attributes: ['name' => 'Kari Kollega']), ['acme:things:read']);
    acmeUser(attributes: ['name' => 'Kunde Uten Verktøy', 'email' => 'kunde@example.com']);

    confirmSend($sender, ['to' => ['benjamin'], 'body' => 'Ferdig med importen.']);

    expect(Message::query()->sole()->to_people)->toBe(['Benjamin Christensen']);

    $ambiguous = confirmSend($sender, ['to' => ['Kari'], 'body' => 'Hei']);
    $customer = confirmSend($sender, ['to' => ['kunde@example.com'], 'body' => 'Hei']);

    expect($ambiguous['isError'] ?? false)->toBeTrue()
        ->and($ambiguous['content'][0]['text'])->toContain('Kari Nordmann')->toContain('Kari Kollega')
        ->and($customer['isError'] ?? false)->toBeTrue()
        ->and($customer['content'][0]['text'])->toContain('report_gap')
        ->and(Message::query()->count())->toBe(1);
});

it('reaches the developer as "dev" or by the configured name, whatever the account is called, and names them', function () {
    config()->set('mcp-kit.people.builder', ['name' => 'Anders Heim', 'aliases' => ['Anders', 'HeiHallo'], 'emails' => ['hello@heihallo.com', 'dev@heihallo.com']]);
    $dev = acmeUser(['staff', 'things', 'admin'], 'admin', ['name' => 'HeiHallo', 'email' => 'dev@heihallo.com']);
    $devToken = acmeToken($dev, ['acme:things:read']);
    $sender = acmeToken(acmeUser(attributes: ['name' => 'Siri Sender']), ['acme:things:read']);

    foreach (['dev', 'Anders', 'hello@heihallo.com'] as $who) {
        expect(confirmSend($sender, ['to' => [$who], 'body' => "Til {$who}"])['isError'] ?? false)->toBeFalse();
    }

    expect(Message::query()->pluck('to_people')->flatten()->unique()->values()->all())->toBe(['HeiHallo'])
        ->and(firstBlock($devToken))->toContain('Til dev');

    $whoami = collect(Mcp::call($sender, '/mcp/acme', 'whoami', [])->json('result.content'))->pluck('text')->implode("\n");

    expect($whoami)->toContain('Anders Heim')->toContain('"dev"');
});
