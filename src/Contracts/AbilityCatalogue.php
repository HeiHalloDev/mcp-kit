<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

interface AbilityCatalogue
{
    /**
     * Grantable abilities with a description, wildcards included;
     * explicit-only abilities are listed by explicitOnly().
     *
     * @return array<string, string>
     */
    public function all(): array;

    /**
     * @return array<string, string>
     */
    public function explicitOnly(): array;

    /**
     * Every grantable name, explicit-only ones included.
     *
     * @return list<string>
     */
    public function names(): array;

    /**
     * @return list<string>
     */
    public function readOnly(): array;

    public function exists(string $ability): bool;

    public function isWrite(string $ability): bool;

    public function isWildcard(string $ability): bool;

    public function isFamilyWildcard(string $ability): bool;

    public function isExplicitOnly(string $ability): bool;

    public function canonical(string $ability): string;

    public function isLegacy(string $ability): bool;

    /**
     * The wildcards that would grant an ability, most specific first.
     * Explicit-only abilities have none.
     *
     * @return list<string>
     */
    public function wildcardsFor(string $ability): array;

    /**
     * The concrete abilities a set of token abilities grants.
     *
     * @param  list<string>  $tokenAbilities
     * @return list<string>
     */
    public function expand(array $tokenAbilities): array;

    /**
     * @return list<string>
     */
    public function requiredPermissions(string $ability): array;

    public function allowedForServiceClient(string $ability): bool;

    public function serverFor(string $ability): ?string;

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    public function serversFor(array $abilities): array;

    /**
     * @return list<string>
     */
    public function referencedPermissions(): array;

    public function description(string $ability): ?string;

    /**
     * Distinct first segments ("crm", "reports") — what the docs scanner
     * and the install scanner look for in tool source.
     *
     * @return list<string>
     */
    public function prefixes(): array;

    /**
     * @return list<string>
     */
    public function serverWildcards(): array;
}
