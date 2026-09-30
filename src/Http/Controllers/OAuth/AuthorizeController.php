<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http\Controllers\OAuth;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\ConfirmsFreshLogin;
use HeiHallo\McpKit\Models\OAuthClient;
use HeiHallo\McpKit\OAuth\AuthorizationServer;
use HeiHallo\McpKit\OAuth\OAuthException;
use HeiHallo\McpKit\OAuth\Pkce;
use HeiHallo\McpKit\OAuth\RedirectUris;
use HeiHallo\McpKit\Servers\ServerDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page a person lands on from their AI client: who is asking, what it
 * will be able to do, and a yes or a no. Sits behind the app's own login
 * (oauth.consent_middleware), so signing in is whatever the app already
 * does — a passkey, an authenticator, the group's single sign-on.
 *
 * Nothing about the request is kept between the page and the answer: the
 * form carries it back and every check runs again on the way in.
 */
class AuthorizeController
{
    public function __construct(
        protected AuthorizationServer $server,
        protected AbilityCatalogue $catalogue,
        protected ConfirmsFreshLogin $confirms,
    ) {}

    public function show(Request $request): Response
    {
        [$client, $redirectUri, $error] = $this->client($request);

        if ($error !== null) {
            return $error;
        }

        try {
            $server = $this->validated($request);
        } catch (OAuthException $e) {
            return $this->back($redirectUri, $request, $e->error, $e->getMessage());
        }

        if ($confirm = $this->confirms->required($request)) {
            return $confirm;
        }

        $offered = $this->server->offered($request->user(), $server);
        $unticked = (array) config('mcp-kit.oauth.unticked', []);

        return response()->view('mcp-kit::oauth.consent', [
            'client' => $client,
            'server' => $server,
            'redirectHost' => (string) parse_url($redirectUri, PHP_URL_HOST),
            'abilities' => array_map(fn (string $ability): array => [
                'name' => $ability,
                'description' => $this->catalogue->description($ability) ?? $ability,
                'writes' => $this->catalogue->isWrite($ability),
                'checked' => ! in_array($ability, $unticked, true),
            ], $offered),
            'params' => $this->params($request),
            'user' => $request->user(),
        ]);
    }

    public function decide(Request $request): Response
    {
        [$client, $redirectUri, $error] = $this->client($request);

        if ($error !== null) {
            return $error;
        }

        try {
            $server = $this->validated($request);
        } catch (OAuthException $e) {
            return $this->back($redirectUri, $request, $e->error, $e->getMessage());
        }

        if ($confirm = $this->confirms->required($request)) {
            return $confirm;
        }

        if ($request->input('decision') !== 'allow') {
            return $this->back($redirectUri, $request, 'access_denied', 'The person declined.');
        }

        $chosen = array_map('strval', (array) $request->input('abilities', []));
        $offered = $this->server->offered($request->user(), $server);
        $abilities = array_values(array_intersect($offered, $chosen));

        if ($abilities === []) {
            return $this->back($redirectUri, $request, 'access_denied', 'Nothing was allowed.');
        }

        // What was on the page and left unticked. Kept, so a sign-in that
        // follows the person's permissions never adds it back.
        $declined = array_values(array_diff($offered, $abilities));

        $code = $this->server->issueCode($client, $request->user(), $abilities, $redirectUri, (string) $request->input('code_challenge'), $server, $declined);

        return $this->redirect($redirectUri, array_filter([
            'code' => $code,
            'state' => $request->input('state'),
            'iss' => url('/'),
        ], fn ($value): bool => $value !== null));
    }

    /**
     * The client and its redirect. Until both check out, nothing may be sent
     * to the redirect address — it could be anybody's — so failures here
     * are shown on the page instead.
     *
     * @return array{0: ?OAuthClient, 1: string, 2: ?Response}
     */
    protected function client(Request $request): array
    {
        $client = OAuthClient::query()->where('client_id', (string) $request->input('client_id'))->first();
        $redirectUri = (string) $request->input('redirect_uri', '');

        if ($client === null) {
            return [null, '', $this->page(__('This app is not registered here. Remove the connection in your AI client and add it again.'))];
        }

        if ($redirectUri === '' && count($client->redirect_uris) === 1) {
            $redirectUri = $client->redirect_uris[0];
        }

        if (! RedirectUris::matches($redirectUri, $client->redirect_uris)) {
            return [$client, '', $this->page(__('The address to return to does not match the one this app registered.'))];
        }

        return [$client, $redirectUri, null];
    }

    /**
     * @throws OAuthException
     */
    protected function validated(Request $request): ServerDefinition
    {
        if ($request->input('response_type') !== 'code') {
            throw new OAuthException('unsupported_response_type', 'Only response_type=code is supported.');
        }

        if ($request->input('code_challenge_method') !== 'S256' || ! Pkce::validChallenge((string) $request->input('code_challenge'))) {
            throw new OAuthException('invalid_request', 'PKCE is required: code_challenge with code_challenge_method=S256.');
        }

        $resource = $request->input('resource');

        return $this->server->server(is_string($resource) ? $resource : null);
    }

    /**
     * @return array<string, string>
     */
    protected function params(Request $request): array
    {
        $keep = ['client_id', 'redirect_uri', 'response_type', 'code_challenge', 'code_challenge_method', 'state', 'resource', 'scope'];

        return array_filter(array_map(
            fn ($value): ?string => is_string($value) ? $value : null,
            $request->only($keep),
        ), fn (?string $value): bool => $value !== null);
    }

    protected function back(string $redirectUri, Request $request, string $error, string $description): RedirectResponse
    {
        return $this->redirect($redirectUri, array_filter([
            'error' => $error,
            'error_description' => $description,
            'state' => $request->input('state'),
            'iss' => url('/'),
        ], fn ($value): bool => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function redirect(string $uri, array $query): RedirectResponse
    {
        $separator = str_contains($uri, '?') ? '&' : '?';

        return redirect()->away($uri.$separator.http_build_query($query));
    }

    protected function page(string $message): Response
    {
        return response()->view('mcp-kit::oauth.error', ['message' => $message], 400);
    }
}
