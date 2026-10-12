# Spec: connect to an mcp-kit server with a URL and a login

Status: proposal, 2026-09-18, written against v1.12.2. Sign-in shipped in v1.20.0 differently: in `mode = local` the app is its own authorization server, and every app on the roster uses that. What follows is still the plan for `mode = delegated`, with the group auth service as authorization server. That mode refuses to boot until it is built. Where this spec and the README disagree, the README describes what runs.

## The problem

Today a person connects to an app's MCP server by minting a Sanctum token on the tokens page and pasting it, with the URL, into their client's config. It works, and it is the right tool for scripts and service clients. For staff it is friction: a page to find, a secret to copy, a config file to edit, and a token to renew every 90 days.

MCP clients (Claude, Claude Code, Cursor, ChatGPT) support a second way in. The person adds only the server URL. The client gets a 401, reads where to log in, registers itself, opens a browser, the person logs in and says yes, and the client holds a token from then on.

## How it fits our setup

Two roles, and we already have both halves:

- **Authorization server**: the group's auth service (auth.theacademy, and auth.afpt since it is the same codebase). It already runs Passport, a login and one account per person. As of `theacademyse/auth` commit `c1e240e` it also has, behind `MCP_CLIENT_REGISTRATION`:
  - `GET /.well-known/oauth-authorization-server` (RFC 8414)
  - `POST /oauth/register` (RFC 7591): public client, PKCE, scope `mcp:use` only, redirects limited to an allow-list of https hosts and to loopback
  - a real consent screen for clients that registered themselves; our own apps still skip consent
  - `POST /api/oauth/token/validate`: given a token, returns `active`, `user_id`, `email`, `name`, `client_id`, `client_name`, `self_registered`, `scope`, `expires_at`. The caller must itself be authenticated (client credentials).
- **Resource server**: each app with an mcp-kit server. This is where the work in this spec is.

`laravel/mcp` ^0.9, which the kit already requires, publishes `/.well-known/oauth-protected-resource` through `Mcp::oauthRoutes()` and reads `config('mcp.authorization_server')` for an authorization server on another host. The kit does not call it yet.

## What changes in mcp-kit

### 1. A second guard in the stack

`McpKit::middleware()` hard-codes `auth:sanctum` (`src/McpKit.php:50`). Replace it with a kit middleware, `AuthenticateMcpRequest`, that tries in order:

1. Sanctum, exactly as today. A Sanctum bearer token never reaches the OAuth path.
2. When `mcp-kit.oauth.enabled` is true: a bearer token that is not a Sanctum token is sent to the token validator (below).

On no credentials, or an invalid token, answer `401` with

```
WWW-Authenticate: Bearer resource_metadata="https://{app}/.well-known/oauth-protected-resource/{server path}"
```

That header is what makes a client start the login. Today's 401 has no header, so clients stop.

### 2. Token validation

New contract `Contracts\OAuthTokenValidator` with one method, `validate(string $token): ?OAuthIdentity`. Default implementation `IntrospectingTokenValidator`:

- calls the auth service's validate endpoint with the app's client-credentials token (apps on `afpt/auth-client` already have `AuthApiClient` for this; the kit must not depend on that package, so the HTTP call and the service token are behind a small `Contracts\AuthServiceClient` the app binds, with an auth-client adapter shipped as a stub)
- requires `active`, the `mcp:use` scope, and an unexpired token
- caches a positive result for 60 seconds keyed by a hash of the token, never the token itself; caches nothing on failure
- `OAuthIdentity` carries `userId`, `email`, `clientId`, `clientName`, `selfRegistered`, `expiresAt`

studies.afpt's `PassportIntrospectionGuard` does the same thing by hand and is the reference.

Known gap: Passport has no resource indicators (RFC 8707), so a token is not bound to one app. A token issued for the CRM's MCP server would validate at another app's MCP server too. What limits it is step 3: the person must exist and hold permissions in that app. Accept for now, note in the README, revisit if Passport gains audience support.

### 3. From identity to principal

`PrincipalResolver::resolve()` takes an `Authenticatable`. Keep that. The guard turns an `OAuthIdentity` into the local user and the resolver builds the principal as today, with two differences:

- **Finding the user**: by id first (our apps keep local user id equal to auth user id), and only when the email also matches; otherwise by email. Never create a user here. No local user means 403 with a plain message: this account has no access to this app.
- **Abilities**: an OAuth token has no ability list. `Principal::abilities()` reads `$this->token?->abilities` (`src/Principal.php:47`). Add an optional `grantedAbilities` list to `Principal`; when set it wins. For OAuth it is computed as: every catalogue ability whose mapped permission the user holds, intersected with the preset named in `mcp-kit.oauth.preset` (default `work`), minus everything in `explicit_only`. So a login can never carry refunds, role changes or broadcasts. Those stay on hand-minted Sanctum tokens, which is the safety we have today.

`EnsureMcpAccess` refuses anything that is not a `PersonalAccessToken` (`src/Http/Middleware/EnsureMcpAccess.php:64`). Change that check to "has a Sanctum token or an OAuth identity". Service clients stay Sanctum only.

### 4. Discovery routes

`McpKit::routes()` (or the service provider, when `oauth.enabled`) calls `Mcp::oauthRoutes()` once and sets `mcp.authorization_server` from `mcp-kit.oauth.authorization_server`. The app registers nothing by hand. The registration endpoint `laravel/mcp` adds under the app's own `/oauth/register` must be disabled or left unrouted: clients register with the auth service, not with each app.

### 5. Audit

`activity_log.token_name` and `mcp_tasks.token_id` assume a Sanctum token. For OAuth write `token_name = "oauth:{clientName}"` and `token_id = "oauth:{clientId}"`. The tokens page gains a read-only list "Connected with login": client name, first seen, last used, from the audit trail. Removing a connection is done on the auth service (revoke the person's tokens for that client); link there.

### 6. Config

```php
'oauth' => [
    'enabled' => env('MCP_KIT_OAUTH', false),
    'authorization_server' => env('MCP_KIT_AUTHORIZATION_SERVER'), // https://auth.theacademy.se
    'preset' => 'work',
    'cache_seconds' => 60,
    'staff_only' => true, // refuse a login whose user is not staff, whatever the server definition says
],
```

Off by default. With it off the kit behaves exactly as v1.12.

## What each app does

1. `composer update heihallo/mcp-kit`
2. Bind `AuthServiceClient` (one line with the shipped auth-client adapter)
3. Set `MCP_KIT_OAUTH=true` and `MCP_KIT_AUTHORIZATION_SERVER`
4. On the auth service: `MCP_CLIENT_REGISTRATION=true`

Then a person adds `https://crm.theacademy.se/mcp/crm` in Claude and logs in.

## Tests the kit needs

- Sanctum path unchanged with `oauth.enabled` both off and on (the existing suite, run twice).
- 401 carries the `WWW-Authenticate` header only when OAuth is on.
- Valid token → principal with abilities equal to permissions ∩ preset − explicit_only. One test per term.
- Inactive, expired, wrong scope, validator down → 401, and nothing cached.
- Known token, no local user → 403. Id match with another email → treated as no match.
- A user blocked locally is refused even with a valid token.
- Service clients cannot use the OAuth path.
- The cache key is never the raw token (assert on the cache store).
- `KitGuardsTest` in the apps keeps passing: every server still has the full guard stack.

## Out of scope

- Step-up for `explicit_only` abilities over OAuth.
- Audience-bound tokens.
- Pruning self-registered clients nobody used (belongs in the auth service: tenantless `mcp` clients with no tokens after 30 days).
- Custom URL scheme redirects (`cursor://`). The auth service accepts https allow-list and loopback only; add schemes there when a client needs one.
