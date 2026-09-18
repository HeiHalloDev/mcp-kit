<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Models\Upload;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
 * MCP carries JSON, not bytes. The endpoint stages a file behind the same
 * token and gate as the servers; a tool consumes the handle and copies the
 * bytes into the app's real home. Staged files belong to the person, not
 * the token, and expire — a loading dock, not a warehouse.
 */

function bootUploads(): void
{
    config()->set('mcp-kit.uploads.enabled', true);
    Storage::fake('local');
    require __DIR__.'/../../routes/uploads.php';
    Route::getRoutes()->refreshNameLookups();
}

function postUpload(?string $token, UploadedFile $file)
{
    return test()->post('/mcp/uploads', ['file' => $file], array_filter([
        'Accept' => 'application/json',
        'Authorization' => $token ? 'Bearer '.$token : null,
    ]));
}

test('a file stages behind the same token and comes back as a handle', function () {
    bootUploads();
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    $response = postUpload($token, UploadedFile::fake()->createWithContent('kursplan.pdf', 'PDF BYTES'))
        ->assertCreated()
        ->assertJsonPath('name', 'kursplan.pdf')
        ->assertJsonPath('checksum', hash('sha256', 'PDF BYTES'));

    $upload = Upload::query()->where('handle', $response->json('upload'))->sole();

    expect($upload->handle)->toStartWith('up_')
        ->and(Storage::disk('local')->exists($upload->path))->toBeTrue()
        ->and($upload->expires_at->isAfter(now()->addDays(2)))->toBeTrue();
});

test('no token, no staging', function () {
    bootUploads();

    postUpload(null, UploadedFile::fake()->create('x.pdf'))->assertUnauthorized();

    expect(Upload::query()->count())->toBe(0);
});

test('too big is refused with both numbers', function () {
    bootUploads();
    config()->set('mcp-kit.uploads.max_kb', 1);

    $token = acmeToken(acmeUser(), ['acme:things:read']);

    postUpload($token, UploadedFile::fake()->create('big.bin', 5))
        ->assertStatus(413)
        ->assertJsonPath('error', fn (string $e) => str_contains($e, '1 KB'));
});

test('a mime allow-list narrows what the app takes', function () {
    bootUploads();
    config()->set('mcp-kit.uploads.mimes', ['application/pdf']);

    $token = acmeToken(acmeUser(), ['acme:things:read']);

    postUpload($token, UploadedFile::fake()->createWithContent('x.txt', 'hi'))
        ->assertStatus(422)
        ->assertJsonPath('error', fn (string $e) => str_contains($e, 'application/pdf'));
});

test('a tool consumes the handle, reads the bytes, and the row says where they went', function () {
    bootUploads();
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read', 'acme:things:write']);
    $thing = Thing::query()->create(['name' => 'Widget']);

    $handle = postUpload($token, UploadedFile::fake()->createWithContent('manual.pdf', 'CONTENTS'))->json('upload');

    Mcp::call($token, '/mcp/acme', 'attach_file', ['id' => $thing->id, 'upload' => $handle])
        ->assertSee('manual.pdf')
        ->assertSee('\"bytes\":8', false);

    $upload = Upload::query()->where('handle', $handle)->sole();

    expect($upload->consumed)->toHaveCount(1)
        ->and($upload->consumed[0]['tool'])->toBe('attach_file')
        ->and($upload->consumed[0]['target'])->toBe('thing#'.$thing->id);
});

test("somebody else's handle answers exactly like a handle that does not exist", function () {
    bootUploads();
    $kari = acmeToken(acmeUser(), ['acme:things:read', 'acme:things:write']);
    $ola = acmeToken(acmeUser(['staff', 'things'], 'staff', ['name' => 'Ola Nordmann']), ['acme:things:write']);
    $thing = Thing::query()->create(['name' => 'Widget']);

    $handle = postUpload($kari, UploadedFile::fake()->createWithContent('privat.pdf', 'X'))->json('upload');

    $foreign = Mcp::call($ola, '/mcp/acme', 'attach_file', ['id' => $thing->id, 'upload' => $handle])->getContent();
    $missing = Mcp::call($ola, '/mcp/acme', 'attach_file', ['id' => $thing->id, 'upload' => 'up_doesnotexist'])->getContent();

    // A handle must not be a probe for whether somebody else uploaded
    // something — same sentence, either way, and never the file name.
    expect($foreign)->toContain('No staged file matches')
        ->and($foreign)->not->toContain('privat.pdf');
    expect($missing)->toContain('No staged file matches');
});

test('ownership survives a token rotation, so a cut-off session picks its files back up', function () {
    bootUploads();
    $user = acmeUser();

    $handle = postUpload(acmeToken($user, ['acme:things:read'], 'old-laptop'),
        UploadedFile::fake()->createWithContent('rapport.csv', 'a;b'))->json('upload');

    // A fresh token, as after a 90-day rotation or a dead session.
    Mcp::call(acmeToken($user, ['acme:things:read'], 'new-laptop'), '/mcp/acme', 'list_uploads')
        ->assertSee($handle)
        ->assertSee('rapport.csv');
});

test('list_uploads shows only your own', function () {
    bootUploads();
    $kari = acmeToken(acmeUser(), ['acme:things:read']);
    $ola = acmeToken(acmeUser(['staff', 'things'], 'staff', ['name' => 'Ola Nordmann']), ['acme:things:read']);

    postUpload($kari, UploadedFile::fake()->createWithContent('karis.pdf', 'K'));

    Mcp::call($ola, '/mcp/acme', 'list_uploads')->assertDontSee('karis.pdf')->assertSee('Nothing staged');
});

test('expired files refuse consumption and prune takes row and bytes', function () {
    bootUploads();
    $token = acmeToken(acmeUser(), ['acme:things:read', 'acme:things:write']);
    $thing = Thing::query()->create(['name' => 'Widget']);

    $handle = postUpload($token, UploadedFile::fake()->createWithContent('gammel.pdf', 'OLD'))->json('upload');
    $upload = Upload::query()->where('handle', $handle)->sole();
    $upload->forceFill(['expires_at' => now()->subDay()])->save();

    Mcp::call($token, '/mcp/acme', 'attach_file', ['id' => $thing->id, 'upload' => $handle])
        ->assertSee('expired');

    test()->artisan('mcp-kit:prune')->assertSuccessful();

    expect(Upload::query()->where('handle', $handle)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($upload->path))->toBeFalse();
});

test('off by default: no route, no tool, no footer sentence', function () {
    expect(config('mcp-kit.uploads.enabled'))->toBeFalse()
        ->and(Route::has('mcp-kit.uploads'))->toBeFalse();

    $token = acmeToken(acmeUser(), ['acme:things:read']);

    expect(collect(Mcp::listTools($token, '/mcp/acme')->json('result.tools'))->pluck('name'))->not->toContain('list_uploads');

    expect(app(GroundRules::class)->instructions('acme', 'Staff tools.'))
        ->not->toContain('/mcp/uploads');
});

test('the connect instructions say how, where uploads are on', function () {
    bootUploads();

    expect(app(GroundRules::class)->instructions('acme', 'Staff tools.'))
        ->toContain('/mcp/uploads')
        ->toContain('list_uploads');
});

/*
 * An assistant behind a connector talks MCP through a token it never sees,
 * so it cannot send the bearer header. request_upload hands it a signed
 * link instead; behind the link everything is the bearer upload again.
 */

function uploadLink(string $token): string
{
    $reply = Mcp::call($token, '/mcp/acme', 'request_upload')->json('result.content.0.text');

    return (string) json_decode((string) $reply, true)['url'];
}

function postToLink(string $url, UploadedFile $file)
{
    return test()->post($url, ['file' => $file], ['Accept' => 'application/json']);
}

test('a link from request_upload stages a file without the bearer token', function () {
    bootUploads();
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read', 'acme:things:write']);
    $thing = Thing::query()->create(['name' => 'Widget']);

    $link = uploadLink($token);

    expect($link)->toContain('/mcp/uploads/link')->toContain('signature=');

    $handle = postToLink($link, UploadedFile::fake()->createWithContent('figur.png', 'PNG BYTES'))
        ->assertCreated()
        ->assertJsonPath('checksum', hash('sha256', 'PNG BYTES'))
        ->json('upload');

    // Staged in the person's own dock, so their tools take it as usual.
    Mcp::call($token, '/mcp/acme', 'attach_file', ['id' => $thing->id, 'upload' => $handle])
        ->assertSee('figur.png');
});

test('one link takes several files until it expires', function () {
    bootUploads();
    $link = uploadLink(acmeToken(acmeUser(), ['acme:things:read']));

    postToLink($link, UploadedFile::fake()->createWithContent('a.png', 'A'))->assertCreated();
    postToLink($link, UploadedFile::fake()->createWithContent('b.png', 'B'))->assertCreated();

    expect(Upload::query()->count())->toBe(2);
});

test('an expired link stores nothing and says to ask again', function () {
    bootUploads();
    $link = uploadLink(acmeToken(acmeUser(), ['acme:things:read']));

    $this->travel(31)->minutes();

    postToLink($link, UploadedFile::fake()->createWithContent('sen.png', 'X'))
        ->assertForbidden()
        ->assertJsonPath('error', fn (string $e) => str_contains($e, 'request_upload'));

    expect(Upload::query()->count())->toBe(0);
});

test('a link pointed at somebody else\'s token fails the signature', function () {
    bootUploads();
    $kari = acmeUser();
    $ola = acmeUser(['staff', 'things'], 'staff', ['name' => 'Ola Nordmann']);
    $link = uploadLink(acmeToken($kari, ['acme:things:read']));
    acmeToken($ola, ['acme:things:read']);
    $olaTokenId = (string) $ola->tokens()->value('id');

    $forged = preg_replace('/([?&]token=)\d+/', '${1}'.$olaTokenId, $link);

    postToLink($forged, UploadedFile::fake()->createWithContent('x.png', 'X'))->assertForbidden();

    expect(Upload::query()->count())->toBe(0);
});

test('revoking the token kills its links', function () {
    bootUploads();
    $user = acmeUser();
    $link = uploadLink(acmeToken($user, ['acme:things:read']));

    $user->tokens()->delete();

    postToLink($link, UploadedFile::fake()->createWithContent('x.png', 'X'))
        ->assertUnauthorized()
        ->assertJsonPath('error', fn (string $e) => str_contains($e, 'request_upload'));

    expect(Upload::query()->count())->toBe(0);
});

test('a person blocked after the link was handed out stages nothing', function () {
    bootUploads();
    $user = acmeUser();
    $link = uploadLink(acmeToken($user, ['acme:things:read']));

    $user->forceFill(['blocked_at' => now()])->save();

    postToLink($link, UploadedFile::fake()->createWithContent('x.png', 'X'))->assertForbidden();

    expect(Upload::query()->count())->toBe(0);
});

test('a link keeps the size limit', function () {
    bootUploads();
    config()->set('mcp-kit.uploads.max_kb', 1);
    $link = uploadLink(acmeToken(acmeUser(), ['acme:things:read']));

    postToLink($link, UploadedFile::fake()->create('big.bin', 5))->assertStatus(413);
});

test('request_upload is off with uploads, and the footer names it when on', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    expect(collect(Mcp::listTools($token, '/mcp/acme')->json('result.tools'))->pluck('name'))->not->toContain('request_upload');

    bootUploads();

    expect(collect(Mcp::listTools($token, '/mcp/acme')->json('result.tools'))->pluck('name'))->toContain('request_upload')
        ->and(app(GroundRules::class)->instructions('acme', 'Staff tools.'))->toContain('request_upload');
});
