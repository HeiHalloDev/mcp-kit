<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Describe;

/**
 * What the app already knows about a person — so getting_started only asks
 * for what is missing.
 */
final class UserDescription
{
    /**
     * @param  list<string>  $inboxes
     * @param  list<string>  $permissions
     * @param  list<string>  $facts  free-form, one line each
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $role,
        public readonly bool $privileged,
        public readonly ?string $team,
        public readonly array $inboxes = [],
        public readonly array $permissions = [],
        public readonly array $facts = [],
    ) {}

    public function knowsRole(): bool
    {
        return $this->role !== null && trim($this->role) !== '';
    }

    public function knowsTeam(): bool
    {
        return $this->team !== null && trim($this->team) !== '';
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(
            name: $changes['name'] ?? $this->name,
            email: array_key_exists('email', $changes) ? $changes['email'] : $this->email,
            role: array_key_exists('role', $changes) ? $changes['role'] : $this->role,
            privileged: $changes['privileged'] ?? $this->privileged,
            team: array_key_exists('team', $changes) ? $changes['team'] : $this->team,
            inboxes: $changes['inboxes'] ?? $this->inboxes,
            permissions: $changes['permissions'] ?? $this->permissions,
            facts: $changes['facts'] ?? $this->facts,
        );
    }
}
