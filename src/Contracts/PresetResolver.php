<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface PresetResolver
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function all(): array;

    /**
     * Every ordinary ability this person may hold.
     *
     * @return list<string>
     */
    public function grantableFor(Authenticatable $user): array;

    /**
     * @return list<string>
     */
    public function abilitiesFor(Authenticatable $user, string $preset): array;

    /**
     * @return array<string, array{label: string, description: string, abilities: list<string>}>
     */
    public function availableFor(Authenticatable $user): array;

    /**
     * Explicit-only abilities this person may add on top of a preset.
     *
     * @return array<string, string>
     */
    public function extrasFor(Authenticatable $user): array;

    /**
     * @param  list<string>  $abilities
     */
    public function labelForAbilities(array $abilities): string;

    /**
     * @param  list<string>  $abilities
     */
    public function grantsWrite(array $abilities): bool;

    /**
     * @param  list<string>  $abilities
     */
    public function grantsExplicitOnly(array $abilities): bool;

    public function expiresDaysFor(string $preset): ?int;
}
