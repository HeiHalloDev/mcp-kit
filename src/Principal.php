<?php

declare(strict_types=1);

namespace HeiHallo\McpKit;

use HeiHallo\McpKit\Enums\PrincipalKind;
use HeiHallo\McpKit\Models\OAuthGrant;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Who is calling: a person (staff user) or a service (another system), plus
 * the facts every guard needs. Resolved once per request by the configured
 * PrincipalResolver so tools, middleware and the audit writer agree.
 */
final class Principal
{
    private string|false|null $frameKey = false;

    public function __construct(
        public readonly PrincipalKind $kind,
        public readonly Authenticatable $tokenable,
        public readonly string $name,
        public readonly ?string $email,
        public readonly bool $blocked,
        public readonly bool $staff,
        public readonly bool $privileged,
        public readonly ?PersonalAccessToken $token,
    ) {}

    public function isPerson(): bool
    {
        return $this->kind === PrincipalKind::Person;
    }

    public function isService(): bool
    {
        return $this->kind === PrincipalKind::Service;
    }

    public function id(): int|string|null
    {
        return $this->tokenable->getAuthIdentifier();
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        $abilities = $this->token?->abilities;

        return is_array($abilities) ? array_values(array_filter($abilities, 'is_string')) : [];
    }

    /**
     * Tolerant of test doubles (Sanctum::actingAs hands out a mock whose
     * properties answer false).
     */
    public function tokenName(): ?string
    {
        $name = $this->token?->name;

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function tokenId(): int|string|null
    {
        $id = $this->token?->id;

        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * What task frames, hints and the frame lock are kept under. The token
     * id, except for a token issued by a sign-in: that one turns over every
     * hour, so its frames belong to the grant, which does not.
     */
    public function frameKey(): ?string
    {
        if ($this->frameKey !== false) {
            return $this->frameKey;
        }

        $tokenId = $this->tokenId();

        if ($tokenId === null) {
            return $this->frameKey = null;
        }

        $prefix = (string) config('mcp-kit.oauth.token_prefix', 'oauth: ');

        if (config('mcp-kit.oauth.enabled') && $prefix !== '' && str_starts_with((string) $this->tokenName(), $prefix)) {
            $grant = OAuthGrant::query()->where('access_token_id', $tokenId)->first();

            if ($grant !== null) {
                return $this->frameKey = $grant->frameKey();
            }
        }

        return $this->frameKey = (string) $tokenId;
    }

    /**
     * When this connection was made: the sign-in for a token a grant
     * issued (it turns over hourly, the grant does not), else the token.
     * A client like claude.ai keeps the tool list it saw then.
     */
    public function connectedAt(): ?\DateTimeInterface
    {
        $tokenId = $this->tokenId();

        if ($tokenId === null) {
            return null;
        }

        $prefix = (string) config('mcp-kit.oauth.token_prefix', 'oauth: ');

        if (config('mcp-kit.oauth.enabled') && $prefix !== '' && str_starts_with((string) $this->tokenName(), $prefix)) {
            $grant = OAuthGrant::query()->where('access_token_id', $tokenId)->first();

            if ($grant !== null) {
                return $grant->created_at;
            }
        }

        $created = $this->token?->created_at;

        return $created instanceof \DateTimeInterface ? $created : null;
    }

    public function tokenExpiresAt(): ?\DateTimeInterface
    {
        $expires = $this->token?->expires_at;

        return $expires instanceof \DateTimeInterface ? $expires : null;
    }

    /**
     * "Kari Nordmann (mcp: laptop)" for previews and log lines.
     */
    public function signature(): string
    {
        $token = $this->tokenName();

        return $token === null ? $this->name : "{$this->name} ({$token})";
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }
}
