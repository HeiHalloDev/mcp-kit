<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\ConfirmsFreshLogin;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\TokenPolicy;
use HeiHallo\McpKit\Livewire\ConnectedApps;
use HeiHallo\McpKit\Models\OAuthClient;
use HeiHallo\McpKit\Models\OAuthGrant;
use HeiHallo\McpKit\OAuth\SsoConfirmsFreshLogin;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

/*
 * Sign in with a URL (oauth.mode = local). The client adds the server URL,
 * gets a 401 that says where to sign in, registers itself, sends the person
 * to a consent page behind the app's own login, and trades the code for a
 * short-lived kit token plus a refresh token that rotates. Off by default,
 * and off means none of it exists.
 */

const CLAUDE_CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

function bootOAuth(array $overrides = []): void
{
    config()->set('mcp-kit.oauth.enabled', true);

    foreach ($overrides as $key => $value) {
        config()->set("mcp-kit.oauth.{$key}", $value);
    }

    require __DIR__.'/../../routes/oauth.php';
    Route::getRoutes()->refreshNameLookups();
}

/**
 * @return array{0: string, 1: string} verifier, challenge
 */
function pkcePair(): array
{
    $verifier = Str::random(64);

    return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
}

function registerClient(array $redirects = [CLAUDE_CALLBACK]): string
{
    return test()->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => $redirects,
        'token_endpoint_auth_method' => 'none',
    ])->assertCreated()->json('client_id');
}

/**
 * @return array<string, string>
 */
function authorizeParams(string $clientId, string $challenge, array $extra = []): array
{
    return [
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => CLAUDE_CALLBACK,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'state' => 'st-123',
        'resource' => url('/mcp/acme'),
        ...$extra,
    ];
}

/**
 * The person signs in and says yes; returns the code from the redirect.
 *
 * @param  list<string>  $abilities
 */
function consent(User $user, string $clientId, string $challenge, array $abilities = ['acme:things:read']): string
{
    $response = test()->actingAs($user)->post('/oauth/authorize', [
        ...authorizeParams($clientId, $challenge),
        'decision' => 'allow',
        'abilities' => $abilities,
    ]);

    $response->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['state'] ?? null)->toBe('st-123');

    return (string) ($query['code'] ?? '');
}

function exchange(string $clientId, string $code, string $verifier): TestResponse
{
    app('auth')->forgetGuards();

    return test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'code' => $code,
        'redirect_uri' => CLAUDE_CALLBACK,
        'code_verifier' => $verifier,
    ]);
}

function refreshWith(string $clientId, string $refresh): TestResponse
{
    app('auth')->forgetGuards();

    return test()->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $clientId,
        'refresh_token' => $refresh,
    ]);
}

/**
 * @return array{client: string, tokens: array<string, mixed>}
 */
function signedIn(User $user, array $abilities = ['acme:things:read']): array
{
    $client = registerClient();
    [$verifier, $challenge] = pkcePair();
    $code = consent($user, $client, $challenge, $abilities);

    return ['client' => $client, 'tokens' => exchange($client, $code, $verifier)->assertOk()->json()];
}

test('off by default: no sign-in routes, and the 401 stays as it was', function () {
    test()->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
    test()->postJson('/oauth/register', [])->assertNotFound();

    Mcp::listTools(null, '/mcp/acme')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');
});

test('on: the 401 says where to sign in, and both documents point back at the app', function () {
    bootOAuth();

    $header = (string) Mcp::listTools(null, '/mcp/acme')->assertUnauthorized()->headers->get('WWW-Authenticate');

    expect($header)->toContain('resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp/acme').'"');

    test()->getJson('/.well-known/oauth-protected-resource/mcp/acme')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp/acme'))
        ->assertJsonPath('authorization_servers.0', url('/'));

    test()->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonPath('token_endpoint_auth_methods_supported', ['none'])
        ->assertJsonPath('registration_endpoint', url('/oauth/register'));
});

test('registration takes known https hosts and loopback, nothing else', function () {
    bootOAuth();

    registerClient([CLAUDE_CALLBACK]);
    registerClient(['http://127.0.0.1:53682/callback']);

    test()->postJson('/oauth/register', ['redirect_uris' => ['https://evil.example/cb']])
        ->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
    test()->postJson('/oauth/register', ['redirect_uris' => ['http://claude.ai/api/mcp/auth_callback']])
        ->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
    test()->postJson('/oauth/register', ['redirect_uris' => [CLAUDE_CALLBACK], 'token_endpoint_auth_method' => 'client_secret_basic'])
        ->assertStatus(400)->assertJsonPath('error', 'invalid_client_metadata');
});

test('the consent page offers what the person may hold on that server, never an explicit-only ability', function () {
    bootOAuth();
    $client = registerClient();
    [, $challenge] = pkcePair();

    test()->actingAs(acmeAdmin())
        ->get('/oauth/authorize?'.http_build_query(authorizeParams($client, $challenge)))
        ->assertOk()
        ->assertSee('Claude wants to use Acme as you')
        ->assertSee('acme:things:read')
        ->assertSee('acme:things:write')
        ->assertDontSee('acme:admin')
        ->assertDontSee('reports:read');
});

test('an unknown client or a foreign redirect is shown on the page, never sent to the redirect', function () {
    bootOAuth();
    $client = registerClient();
    [, $challenge] = pkcePair();

    test()->actingAs(acmeUser())
        ->get('/oauth/authorize?'.http_build_query(authorizeParams('mcpc_nobody', $challenge)))
        ->assertStatus(400)->assertSee('not registered');

    test()->actingAs(acmeUser())
        ->get('/oauth/authorize?'.http_build_query(authorizeParams($client, $challenge, ['redirect_uri' => 'https://claude.ai/elsewhere'])))
        ->assertStatus(400)->assertSee('does not match');
});

test('without PKCE S256 the client is sent back with invalid_request', function () {
    bootOAuth();
    $client = registerClient();

    $response = test()->actingAs(acmeUser())
        ->get('/oauth/authorize?'.http_build_query(authorizeParams($client, 'short', ['code_challenge_method' => 'plain'])));

    expect((string) $response->headers->get('Location'))->toStartWith(CLAUDE_CALLBACK.'?')->toContain('error=invalid_request');
});

test('the full sign-in gives a short-lived kit token that works on the server', function () {
    bootOAuth();
    $user = acmeUser();

    ['tokens' => $tokens] = signedIn($user);

    expect($tokens['token_type'])->toBe('Bearer')
        ->and($tokens['expires_in'])->toBe(3600)
        ->and($tokens['refresh_token'])->toStartWith('mcpr_')
        ->and($tokens['scope'])->toBe('acme:things:read');

    Mcp::listTools($tokens['access_token'], '/mcp/acme')->assertOk();

    $token = PersonalAccessToken::findToken($tokens['access_token']);

    expect($token->name)->toBe('oauth: Claude')
        ->and($token->abilities)->toBe(['acme:things:read'])
        ->and($token->expires_at->isBetween(now()->addMinutes(59), now()->addMinutes(61)))->toBeTrue();
});

test('a person cannot be given more than they hold, whatever the form says', function () {
    bootOAuth();
    $client = registerClient();
    [$verifier, $challenge] = pkcePair();

    $code = consent(acmeUser(), $client, $challenge, ['acme:things:read', 'acme:admin', 'reports:read']);

    expect(exchange($client, $code, $verifier)->assertOk()->json('scope'))->toBe('acme:things:read');
});

test('declining sends the client back with access_denied', function () {
    bootOAuth();
    $client = registerClient();
    [, $challenge] = pkcePair();

    $response = test()->actingAs(acmeUser())->post('/oauth/authorize', [...authorizeParams($client, $challenge), 'decision' => 'deny']);

    expect((string) $response->headers->get('Location'))->toContain('error=access_denied')->toContain('state=st-123');
});

test('a wrong verifier, a used code and a mismatched redirect are all refused', function () {
    bootOAuth();
    $user = acmeUser();
    $client = registerClient();
    [$verifier, $challenge] = pkcePair();

    $code = consent($user, $client, $challenge);
    exchange($client, $code, Str::random(64))->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

    $code = consent($user, $client, $challenge);
    exchange($client, $code, $verifier)->assertOk();
    exchange($client, $code, $verifier)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

    expect(OAuthGrant::query()->active()->count())->toBe(0);

    $code = consent($user, $client, $challenge);
    test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client,
        'code' => $code,
        'redirect_uri' => 'https://claude.ai/other',
        'code_verifier' => $verifier,
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});

test('refresh rotates the pair and kills the old access token', function () {
    bootOAuth();
    ['client' => $client, 'tokens' => $first] = signedIn(acmeUser());

    $second = refreshWith($client, $first['refresh_token'])->assertOk()->json();

    expect($second['access_token'])->not->toBe($first['access_token'])
        ->and($second['refresh_token'])->not->toBe($first['refresh_token'])
        ->and(PersonalAccessToken::findToken($first['access_token']))->toBeNull();

    Mcp::listTools($second['access_token'], '/mcp/acme')->assertOk();
});

test('the old refresh token answers with the same new pair inside the grace window, and ends the grant after it', function () {
    bootOAuth();
    ['client' => $client, 'tokens' => $first] = signedIn(acmeUser());

    $second = refreshWith($client, $first['refresh_token'])->assertOk()->json();

    expect(refreshWith($client, $first['refresh_token'])->assertOk()->json('access_token'))->toBe($second['access_token']);

    $this->travel(2)->minutes();

    refreshWith($client, $first['refresh_token'])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

    expect(OAuthGrant::query()->sole()->revoked_reason)->toBe('refresh_reuse')
        ->and(PersonalAccessToken::findToken($second['access_token']))->toBeNull();

    refreshWith($client, $second['refresh_token'])->assertStatus(400);
});

test('an ability the person lost drops out at the next refresh, and losing all of them ends the grant', function () {
    bootOAuth();
    $user = acmeUser();
    ['client' => $client, 'tokens' => $tokens] = signedIn($user, ['acme:things:read']);

    $user->forceFill(['permissions' => ['staff']])->save();

    refreshWith($client, $tokens['refresh_token'])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

    expect(OAuthGrant::query()->sole()->revoked_reason)->toBe('no_abilities_left');
});

test('revoking either token ends the grant', function () {
    bootOAuth();
    ['client' => $client, 'tokens' => $tokens] = signedIn(acmeUser());

    test()->postJson('/oauth/revoke', ['client_id' => $client, 'token' => $tokens['refresh_token']])->assertOk();

    expect(OAuthGrant::query()->sole()->isActive())->toBeFalse()
        ->and(PersonalAccessToken::findToken($tokens['access_token']))->toBeNull();
});

test('tokens from a sign-in stay off the tokens page', function () {
    bootOAuth();
    $user = acmeUser();
    signedIn($user);
    Mcp::token($user, ['acme:things:read'], 'laptop');

    $names = $user->tokens()->pluck('name')->filter(fn (string $name): bool => app(TokenPolicy::class)->isKitToken($name));

    expect($names->values()->all())->toBe(['mcp: laptop']);
});

test('frames belong to the grant, so they survive the hourly rotation', function () {
    bootOAuth();
    $user = acmeUser();
    ['client' => $client, 'tokens' => $first] = signedIn($user);
    $second = refreshWith($client, $first['refresh_token'])->assertOk()->json();

    $keyFor = function (string $plain) use ($user): ?string {
        $token = PersonalAccessToken::findToken($plain);

        return app(PrincipalResolver::class)->resolve($user->fresh()->withAccessToken($token))->frameKey();
    };

    expect($keyFor($second['access_token']))->toBe('oauth:'.OAuthGrant::query()->sole()->id);

    $pasted = Mcp::token($user, ['acme:things:read']);

    expect($keyFor($pasted))->toBe((string) PersonalAccessToken::findToken($pasted)->id);
});

test('with confirm_minutes set, a stale login is sent to confirm first', function () {
    bootOAuth();
    Route::get('/confirm-password', fn () => 'confirm')->middleware('web')->name('password.confirm');
    Route::getRoutes()->refreshNameLookups();
    $client = registerClient();
    [, $challenge] = pkcePair();

    test()->actingAs(acmeUser())
        ->get('/oauth/authorize?'.http_build_query(authorizeParams($client, $challenge)))
        ->assertRedirect('/confirm-password');

    test()->actingAs(acmeUser())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/oauth/authorize?'.http_build_query(authorizeParams($client, $challenge)))
        ->assertOk();
});

test('prune ends expired grants and forgets idle clients', function () {
    bootOAuth();
    ['client' => $client] = signedIn(acmeUser());

    $this->travel(31)->days();
    test()->artisan('mcp-kit:prune')->assertSuccessful();

    expect(OAuthGrant::query()->sole()->revoked_reason)->toBe('refresh_expired');

    $this->travel(100)->days();
    test()->artisan('mcp-kit:prune')->assertSuccessful();

    expect(OAuthClient::query()->where('client_id', $client)->exists())->toBeFalse();
});

test('Connected apps lists a person\'s own sign-ins and disconnects one at once', function () {
    bootOAuth();
    $user = acmeUser();
    $other = acmeUser();
    ['tokens' => $tokens] = signedIn($user);
    signedIn($other);

    test()->actingAs($user);

    $component = Livewire\Livewire::test(ConnectedApps::class)
        ->assertSee('Claude')
        ->assertSee('Search and view things');

    expect($component->instance()->grants)->toHaveCount(1);

    $mine = OAuthGrant::query()->where('user_id', (string) $user->id)->sole();
    $theirs = OAuthGrant::query()->where('user_id', (string) $other->id)->sole();

    expect(fn () => $component->call('disconnect', $theirs->id))->toThrow(ModelNotFoundException::class);
    $component->call('disconnect', $mine->id)->assertSee('No assistants are connected');

    expect($mine->fresh()->revoked_reason)->toBe('person_revoked')
        ->and($theirs->fresh()->isActive())->toBeTrue()
        ->and(PersonalAccessToken::findToken($tokens['access_token']))->toBeNull();
});

test('with follow_permissions, an ability the person gains joins at the next refresh', function () {
    bootOAuth(['follow_permissions' => true]);
    $user = acmeUser(['staff']);
    ['client' => $client, 'tokens' => $tokens] = signedIn($user, ['acme:events:write']);

    expect($tokens['scope'])->toBe('acme:events:write');

    $user->forceFill(['permissions' => ['staff', 'things']])->save();

    $next = refreshWith($client, $tokens['refresh_token'])->assertOk()->json();

    expect(explode(' ', $next['scope']))->toEqualCanonicalizing(['acme:events:write', 'acme:things:read', 'acme:things:write'])
        ->and(OAuthGrant::query()->sole()->abilities)->toEqualCanonicalizing(['acme:events:write', 'acme:things:read', 'acme:things:write']);
});

test('a sign-in that follows permissions never adds what the person unticked, nor what unticked holds back', function () {
    bootOAuth(['follow_permissions' => true, 'unticked' => ['acme:things:write']]);
    $user = acmeUser(['staff', 'things']);
    ['client' => $client, 'tokens' => $tokens] = signedIn($user, ['acme:things:read']);

    expect(OAuthGrant::query()->sole()->declined)->toEqualCanonicalizing(['acme:things:write', 'acme:events:write']);

    expect(refreshWith($client, $tokens['refresh_token'])->assertOk()->json('scope'))->toBe('acme:things:read');
});

test('without follow_permissions a sign-in stays at what was consented to', function () {
    bootOAuth();
    $user = acmeUser(['staff']);
    ['client' => $client, 'tokens' => $tokens] = signedIn($user, ['acme:events:write']);

    $user->forceFill(['permissions' => ['staff', 'things']])->save();

    expect(refreshWith($client, $tokens['refresh_token'])->assertOk()->json('scope'))->toBe('acme:events:write');
});

test('an SSO app sends a stale sign-in back through its login, and lets a fresh one through', function () {
    bootOAuth(['confirms' => SsoConfirmsFreshLogin::class]);
    app()->bind(ConfirmsFreshLogin::class, SsoConfirmsFreshLogin::class);
    Route::get('/sso-login', fn () => 'to the auth service')->middleware('web')->name('login');
    Route::getRoutes()->refreshNameLookups();
    $client = registerClient();
    [, $challenge] = pkcePair();
    $url = '/oauth/authorize?'.http_build_query(authorizeParams($client, $challenge));

    test()->actingAs(acmeUser())->get($url)->assertRedirect('/sso-login');

    test()->actingAs(acmeUser())
        ->withSession(['mcp-kit.login_at' => time()])
        ->get($url)
        ->assertOk();

    // Back from the login with no stamp: the callback does not call stamp().
    test()->flushSession();
    test()->actingAs(acmeUser())
        ->withSession([SsoConfirmsFreshLogin::SENT => time()])
        ->get($url)
        ->assertStatus(409);
});
