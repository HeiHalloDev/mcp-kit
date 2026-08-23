<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use Carbon\CarbonInterface;

interface TokenPolicy
{
    public function name(string $label): string;

    public function isKitToken(string $tokenName): bool;

    /**
     * The label without the prefix, for display.
     */
    public function label(string $tokenName): string;

    public function expiresAt(?int $days): ?CarbonInterface;

    public function defaultDays(): ?int;

    public function maxDays(): ?int;

    public function defaultPreset(): string;
}
