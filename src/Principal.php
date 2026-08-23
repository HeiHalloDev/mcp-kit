<?php

declare(strict_types=1);

namespace HeiHallo\McpKit;

use HeiHallo\McpKit\Enums\PrincipalKind;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Who is calling: a person (staff user) or a service (another system), plus
 * the facts every guard needs. Resolved once per request by the configured
 * PrincipalResolver so tools, middleware and the audit writer agree.
 */
final class Principal
{
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
