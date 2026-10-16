<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\OAuth;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Exceptions\TokenRefused;
use HeiHallo\McpKit\Messages\Messages;
use HeiHallo\McpKit\Models\OAuthClient;
use HeiHallo\McpKit\Models\OAuthCode;
use HeiHallo\McpKit\Models\OAuthGrant;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Servers\ServerDefinition;
use HeiHallo\McpKit\Servers\ServerRegistry;
use HeiHallo\McpKit\Tokens\TokenMinter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The authorization server behind oauth.mode = local: OAuth 2.1 with public
 * clients only. Authorization code with PKCE (S256, required) and a refresh
 * token that rotates. No implicit grant, no password grant, no client
 * secrets — a client proves each sign-in with the verifier it kept.
 *
 * The access token is an ordinary kit token minted through TokenMinter, so
 * every guard downstream treats it exactly like a pasted one: the owner must
 * still hold each permission on every call, and a blocked owner is refused.
 */
class AuthorizationServer
{
    public function __construct(
        protected TokenMinter $minter,
        protected AbilityCatalogue $catalogue,
        protected PresetResolver $presets,
        protected PrincipalResolver $principals,
        protected ServerRegistry $servers,
        protected AuditWriter $audit,
    ) {}

    /**
     * RFC 7591 dynamic registration.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws OAuthException
     */
    public function register(array $input, ?string $ip): OAuthClient
    {
        $uris = $input['redirect_uris'] ?? null;

        if (! is_array($uris) || $uris === [] || count($uris) > 10) {
            throw new OAuthException('invalid_redirect_uri', 'redirect_uris must list between one and ten addresses.');
        }

        foreach ($uris as $uri) {
            if (! is_string($uri) || strlen($uri) > 2000 || ! RedirectUris::allowed($uri)) {
                throw new OAuthException('invalid_redirect_uri', 'A redirect address must be https on a permitted host, or loopback.');
            }
        }

        $method = $input['token_endpoint_auth_method'] ?? 'none';

        if ($method !== 'none') {
            throw new OAuthException('invalid_client_metadata', 'Only public clients are accepted (token_endpoint_auth_method none, with PKCE).');
        }

        $name = trim((string) ($input['client_name'] ?? ''));

        return OAuthClient::query()->create([
            'client_id' => 'mcpc_'.Str::random(32),
            'name' => mb_substr($name !== '' ? strip_tags($name) : 'MCP client', 0, 100),
            'redirect_uris' => array_values(array_unique($uris)),
            'registered_ip' => $ip,
        ]);
    }

    /**
     * @throws OAuthException
     */
    public function client(string $clientId): OAuthClient
    {
        $client = OAuthClient::query()->where('client_id', $clientId)->first();

        if ($client === null) {
            throw new OAuthException('invalid_client', 'Unknown client. Register again.', 401);
        }

        return $client;
    }

    /**
     * The server a sign-in is for, from the RFC 8707 resource. A client that
     * sends none gets the only server there is; with several, it must say.
     *
     * @throws OAuthException
     */
    public function server(?string $resource): ServerDefinition
    {
        $servers = $this->servers->canonical();

        if ($resource === null || $resource === '') {
            if (count($servers) === 1) {
                return array_values($servers)[0];
            }

            throw new OAuthException('invalid_target', 'Say which server this is for (the resource parameter).');
        }

        $wanted = rtrim($resource, '/');

        foreach ($this->servers->all() as $definition) {
            if (rtrim($definition->url(), '/') === $wanted) {
                return $this->servers->get($definition->effectiveKey()) ?? $definition;
            }
        }

        throw new OAuthException('invalid_target', 'That resource is not an MCP server of this app.');
    }

    /**
     * What the consent screen offers this person for this server: the
     * configured preset, narrowed to the server, never a wildcard. With
     * oauth.offer_explicit (default on), explicit-only abilities the person
     * could hold anyway are offered too: privileged staff with the
     * permission, nobody else. The page shows them unticked, and a sign-in
     * that follows permissions never adds one by itself.
     *
     * @return list<string>
     */
    public function offered(Authenticatable $user, ServerDefinition $server): array
    {
        $principal = $this->principals->resolve($user);

        if ($principal === null || ! $principal->isPerson() || $principal->blocked) {
            return [];
        }

        $offered = [];

        $candidates = $this->presets->abilitiesFor($user, (string) config('mcp-kit.oauth.preset', 'work'));

        if (config('mcp-kit.oauth.offer_explicit', true)) {
            $candidates = [...$candidates, ...array_keys($this->catalogue->explicitOnly())];
        }

        foreach ($candidates as $ability) {
            if (! $this->catalogue->exists($ability)
                || $this->catalogue->isWildcard($ability)
                || ($this->catalogue->isExplicitOnly($ability) && ! config('mcp-kit.oauth.offer_explicit', true))
                || $this->catalogue->serverFor($ability) !== $server->key) {
                continue;
            }

            try {
                $this->minter->validate($principal, [$ability]);
                $offered[] = $ability;
            } catch (TokenRefused) {
                continue;
            }
        }

        return array_values(array_unique($offered));
    }

    /**
     * The person said yes. Returns the one-time code for the redirect.
     * $declined is what they unticked: a sign-in that follows their
     * permissions never adds those back.
     *
     * @param  list<string>  $abilities
     * @param  list<string>  $declined
     */
    public function issueCode(OAuthClient $client, Authenticatable $user, array $abilities, string $redirectUri, string $challenge, ServerDefinition $server, array $declined = []): string
    {
        $code = Str::random(64);

        OAuthCode::query()->create([
            'code_hash' => hash('sha256', $code),
            'client_id' => $client->getKey(),
            'user_id' => (string) $user->getAuthIdentifier(),
            'abilities' => array_values($abilities),
            'declined' => array_values($declined),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $challenge,
            'resource' => $server->key,
            'expires_at' => Carbon::now()->addSeconds((int) config('mcp-kit.oauth.code_seconds', 60)),
        ]);

        return $code;
    }

    /**
     * grant_type=authorization_code.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthException
     */
    public function exchangeCode(string $clientId, string $code, string $redirectUri, string $verifier): array
    {
        $client = $this->client($clientId);

        return $this->committed(fn (): array => $this->exchangeInTransaction($client, $code, $redirectUri, $verifier));
    }

    /**
     * The code is spent before anything else is checked, so a failed attempt
     * cannot be retried with a better guess.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthException
     */
    protected function exchangeInTransaction(OAuthClient $client, string $code, string $redirectUri, string $verifier): array
    {
        $row = OAuthCode::query()->where('code_hash', hash('sha256', $code))->lockForUpdate()->first();

        if ($row === null || $row->client_id !== $client->getKey()) {
            throw new OAuthException('invalid_grant', 'The code is not valid.');
        }

        if ($row->used_at !== null) {
            // A code presented twice was intercepted or replayed: whatever
            // it produced is no longer trustworthy.
            OAuthGrant::query()->active()
                ->where('client_id', $client->getKey())
                ->where('user_id', $row->user_id)
                ->where('created_at', '>=', $row->used_at)
                ->get()
                ->each(fn (OAuthGrant $grant) => $this->revoke($grant, 'code_reuse'));

            throw new OAuthException('invalid_grant', 'The code has already been used.');
        }

        $row->forceFill(['used_at' => Carbon::now()])->save();

        if ($row->expires_at->isPast()) {
            throw new OAuthException('invalid_grant', 'The code has expired. Sign in again.');
        }

        if (! RedirectUris::matches($redirectUri, [$row->redirect_uri])) {
            throw new OAuthException('invalid_grant', 'redirect_uri does not match the sign-in.');
        }

        if (! Pkce::verifies($verifier, $row->code_challenge)) {
            throw new OAuthException('invalid_grant', 'code_verifier does not match the code_challenge.');
        }

        $user = $this->user($row->user_id);

        $grant = OAuthGrant::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $row->user_id,
            'abilities' => $row->abilities,
            'declined' => $row->declined ?? [],
            'resource' => $row->resource,
        ]);

        $client->forceFill(['last_used_at' => Carbon::now()])->save();

        $response = $this->issue($grant, $user, $client);

        $this->audit->recordToken('oauth_granted', $this->accessToken($grant), $this->principals->resolve($user), [
            'client' => $client->name,
            'client_id' => $client->client_id,
            'grant_id' => $grant->getKey(),
        ]);

        return $response;
    }

    /**
     * grant_type=refresh_token. The presented token is swapped for a new
     * pair. Presented again within the grace window (a retry, or two
     * requests racing), it gets the same new pair back. Within the reuse
     * window it still does, with a fresh access token when the first has
     * expired: ChatGPT and Codex kept sending the token they had just
     * swapped for minutes afterwards, and a 60-second window revoked one
     * staff member's sign-in five times in four days, silently. Later than
     * that it is a stolen token being replayed, and the whole grant is
     * revoked, and its owner is told.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthException
     */
    public function refresh(string $clientId, string $refreshToken): array
    {
        $client = $this->client($clientId);
        $hash = hash('sha256', $refreshToken);

        return $this->committed(function () use ($client, $hash): array {
            $grant = OAuthGrant::query()->active()->where('refresh_hash', $hash)->lockForUpdate()->first();

            if ($grant !== null) {
                if ($grant->client_id !== $client->getKey()) {
                    throw new OAuthException('invalid_grant', 'The refresh token is not valid.');
                }

                if ($grant->refresh_expires_at !== null && $grant->refresh_expires_at->isPast()) {
                    $this->revoke($grant, 'refresh_expired');

                    throw new OAuthException('invalid_grant', 'The sign-in has expired. Sign in again.');
                }

                $user = $this->user($grant->user_id);
                $previous = $this->accessToken($grant);
                $response = $this->issue($grant, $user, $client, previousRefreshHash: $hash);

                $previous?->delete();

                return $response;
            }

            $grant = OAuthGrant::query()->active()->where('previous_refresh_hash', $hash)->lockForUpdate()->first();

            if ($grant !== null && $grant->client_id === $client->getKey()) {
                $grace = (int) config('mcp-kit.oauth.refresh_grace_seconds', 60);

                if ($grant->rotated_at !== null && $grant->rotated_at->diffInSeconds(Carbon::now()) <= $grace && $grant->grace_payload !== null) {
                    return json_decode(Crypt::decryptString($grant->grace_payload), true);
                }

                $window = (int) config('mcp-kit.oauth.refresh_reuse_minutes', 1440) * 60;

                if ($grant->rotated_at !== null && $grant->rotated_at->diffInSeconds(Carbon::now()) <= $window && $grant->grace_payload !== null) {
                    return $this->current($grant, $client);
                }

                $this->revoke($grant, 'refresh_reuse');
                $this->tellRevoked($grant, $client);
            }

            throw new OAuthException('invalid_grant', 'The refresh token is not valid.');
        });
    }

    /**
     * The pair the grant holds now, for a client that came back with the
     * token it had already swapped. Same refresh token, so both copies of
     * the client agree again; a new access token when the old one is gone.
     *
     * @return array<string, mixed>
     */
    protected function current(OAuthGrant $grant, OAuthClient $client): array
    {
        $payload = (array) json_decode(Crypt::decryptString((string) $grant->grace_payload), true);
        $token = $this->accessToken($grant);

        if ($token !== null && ($token->expires_at === null || $token->expires_at->isFuture())) {
            $payload['expires_in'] = $token->expires_at !== null ? max(0, (int) Carbon::now()->diffInSeconds($token->expires_at)) : $payload['expires_in'] ?? null;

            return $payload;
        }

        $user = $this->user($grant->user_id);
        $minutes = max(5, (int) config('mcp-kit.oauth.access_minutes', 60));
        $abilities = array_values(array_filter(explode(' ', (string) ($payload['scope'] ?? ''))));

        try {
            $fresh = $this->minter->mintForGrant($user, $client->name, $abilities, Carbon::now()->addMinutes($minutes));
        } catch (TokenRefused $e) {
            $this->revoke($grant, 'refused');

            throw new OAuthException('invalid_grant', $e->getMessage());
        }

        $token?->delete();

        $payload['access_token'] = $fresh->plainTextToken;
        $payload['expires_in'] = $minutes * 60;

        $grant->forceFill([
            'access_token_id' => $fresh->accessToken->getKey(),
            'grace_payload' => Crypt::encryptString((string) json_encode($payload)),
            'last_used_at' => Carbon::now(),
        ])->save();

        return $payload;
    }

    /**
     * A revoked sign-in used to vanish without a word: the person found out
     * when a colleague could not reach them. Now they hear why, through
     * their own assistant, the next time they connect.
     */
    protected function tellRevoked(OAuthGrant $grant, OAuthClient $client): void
    {
        try {
            $user = $this->user($grant->user_id);
            $name = (string) ($user->getAttribute('name') ?? '');

            if ($name === '') {
                return;
            }

            $page = config('mcp-kit.ui.memory_url') ?: (Route::has('mcp-kit.tokens') ? route('mcp-kit.tokens') : null);

            app(Messages::class)->send(
                sprintf(
                    'The %s sign-in to this app was closed on %s, because a sign-in key that had already been replaced was used again much later, which is what a stolen key looks like. If it was you, nothing is wrong: connect again%s, and tell the people who run the app if it keeps happening.',
                    $client->name,
                    Carbon::now()->format('j M Y H:i'),
                    $page ? ' from '.$page : '',
                ),
                [$name],
                null,
                false,
                $grant->resource ?: null,
                (string) config('app.name', 'this app'),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Run inside a transaction whose writes survive a refusal. A replayed
     * code or refresh token revokes the grant and then refuses the caller;
     * a plain DB::transaction would roll the revocation back with the
     * exception, and the thief would keep the grant.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws OAuthException
     */
    protected function committed(callable $callback): mixed
    {
        $result = DB::transaction(function () use ($callback): mixed {
            try {
                return $callback();
            } catch (OAuthException $e) {
                return $e;
            }
        });

        if ($result instanceof OAuthException) {
            throw $result;
        }

        return $result;
    }

    /**
     * End a grant: the access token dies now, the refresh token with it.
     */
    public function revoke(OAuthGrant $grant, string $reason, ?Principal $by = null): void
    {
        if (! $grant->isActive()) {
            return;
        }

        $token = $this->accessToken($grant);

        if ($token !== null) {
            $this->audit->recordToken('oauth_revoked', $token, $by, [
                'client' => $grant->client?->name,
                'grant_id' => $grant->getKey(),
                'reason' => $reason,
            ]);

            $token->delete();
        }

        $grant->forceFill([
            'revoked_at' => Carbon::now(),
            'revoked_reason' => $reason,
            'access_token_id' => null,
            'refresh_hash' => null,
            'previous_refresh_hash' => null,
            'grace_payload' => null,
        ])->save();
    }

    /**
     * RFC 7009: a refresh token or an access token; either ends the grant.
     * Always succeeds from the client's point of view.
     */
    public function revokeToken(string $clientId, string $token): void
    {
        $client = OAuthClient::query()->where('client_id', $clientId)->first();

        if ($client === null) {
            return;
        }

        $grant = OAuthGrant::query()->active()->where('client_id', $client->getKey())
            ->where(fn ($query) => $query->where('refresh_hash', hash('sha256', $token))->orWhere('previous_refresh_hash', hash('sha256', $token)))
            ->first();

        if ($grant === null) {
            $access = PersonalAccessToken::findToken($token);
            $grant = $access !== null
                ? OAuthGrant::query()->active()->where('client_id', $client->getKey())->where('access_token_id', $access->getKey())->first()
                : null;
        }

        if ($grant !== null) {
            $this->revoke($grant, 'client_revoked');
        }
    }

    /**
     * A new access token and refresh token for the grant. Abilities the
     * person no longer holds drop out here, at the next hour at the latest,
     * on top of the check every call already makes.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthException
     */
    protected function issue(OAuthGrant $grant, Authenticatable $user, OAuthClient $client, ?string $previousRefreshHash = null): array
    {
        $principal = $this->principals->resolve($user);
        $abilities = [];

        foreach ((array) $grant->abilities as $ability) {
            try {
                if ($principal !== null) {
                    $this->minter->validate($principal, [(string) $ability]);
                    $abilities[] = (string) $ability;
                }
            } catch (TokenRefused) {
                continue;
            }
        }

        $abilities = $this->widened($grant, $user, $abilities);

        if ($abilities === []) {
            $this->revoke($grant, 'no_abilities_left');

            throw new OAuthException('invalid_grant', 'This account no longer has access to anything this sign-in covered.');
        }

        $minutes = max(5, (int) config('mcp-kit.oauth.access_minutes', 60));

        try {
            $token = $this->minter->mintForGrant($user, $client->name, $abilities, Carbon::now()->addMinutes($minutes));
        } catch (TokenRefused $e) {
            $this->revoke($grant, 'refused');

            throw new OAuthException('invalid_grant', $e->getMessage());
        }

        $refresh = 'mcpr_'.Str::random(64);

        $response = [
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $minutes * 60,
            'refresh_token' => $refresh,
            'scope' => implode(' ', $abilities),
        ];

        $grant->forceFill([
            'access_token_id' => $token->accessToken->getKey(),
            'refresh_hash' => hash('sha256', $refresh),
            'previous_refresh_hash' => $previousRefreshHash,
            'grace_payload' => $previousRefreshHash !== null ? Crypt::encryptString((string) json_encode($response)) : null,
            'rotated_at' => $previousRefreshHash !== null ? Carbon::now() : $grant->rotated_at,
            'refresh_expires_at' => Carbon::now()->addDays(max(1, (int) config('mcp-kit.oauth.refresh_days', 30))),
            'last_used_at' => Carbon::now(),
        ])->save();

        return $response;
    }

    /**
     * With oauth.follow_permissions, a sign-in grows with the person: an
     * ability they have gained since consent, and that consent would offer
     * them today, joins at the next refresh — within the hour, without
     * signing in again. Never one they unticked, and never one of
     * oauth.unticked: the sensitive ones still need a yes on the page.
     *
     * @param  list<string>  $abilities  what the grant still holds
     * @return list<string>
     */
    protected function widened(OAuthGrant $grant, Authenticatable $user, array $abilities): array
    {
        if (! config('mcp-kit.oauth.follow_permissions', false)) {
            return $abilities;
        }

        $server = $grant->resource !== null ? $this->servers->get((string) $grant->resource) : null;

        if ($server === null) {
            return $abilities;
        }

        $held = (array) $grant->abilities;
        $refused = [...(array) ($grant->declined ?? []), ...(array) config('mcp-kit.oauth.unticked', []), ...array_keys($this->catalogue->explicitOnly())];
        $gained = array_values(array_diff($this->offered($user, $server), $held, $refused));

        if ($gained === []) {
            return $abilities;
        }

        $grant->forceFill(['abilities' => array_values(array_unique([...$held, ...$gained]))])->save();

        return array_values(array_unique([...$abilities, ...$gained]));
    }

    protected function accessToken(OAuthGrant $grant): ?PersonalAccessToken
    {
        return $grant->access_token_id !== null ? PersonalAccessToken::query()->find($grant->access_token_id) : null;
    }

    /**
     * @throws OAuthException
     */
    protected function user(string $id): Authenticatable
    {
        $model = config('auth.providers.users.model');
        $user = is_string($model) && class_exists($model) ? $model::query()->find($id) : null;

        if (! $user instanceof Authenticatable) {
            throw new OAuthException('invalid_grant', 'The account behind this sign-in no longer exists.');
        }

        return $user;
    }
}
