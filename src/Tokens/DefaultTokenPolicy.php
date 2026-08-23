<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tokens;

use Carbon\CarbonInterface;
use HeiHallo\McpKit\Contracts\TokenPolicy;
use Illuminate\Support\Carbon;

class DefaultTokenPolicy implements TokenPolicy
{
    protected function prefix(): string
    {
        return (string) config('mcp-kit.tokens.name_prefix', 'mcp: ');
    }

    public function name(string $label): string
    {
        return $this->prefix().ltrim($label);
    }

    public function isKitToken(string $tokenName): bool
    {
        return $this->prefix() === '' || str_starts_with($tokenName, $this->prefix());
    }

    public function label(string $tokenName): string
    {
        return $this->isKitToken($tokenName) ? substr($tokenName, strlen($this->prefix())) : $tokenName;
    }

    public function expiresAt(?int $days): ?CarbonInterface
    {
        $days ??= $this->defaultDays();

        if ($days === null || $days <= 0) {
            return null;
        }

        $max = $this->maxDays();

        if ($max !== null && $days > $max) {
            $days = $max;
        }

        return Carbon::now()->addDays($days);
    }

    public function defaultDays(): ?int
    {
        $days = config('mcp-kit.tokens.default_days');

        return $days === null ? null : (int) $days;
    }

    public function maxDays(): ?int
    {
        $days = config('mcp-kit.tokens.max_days');

        return $days === null ? null : (int) $days;
    }

    public function defaultPreset(): string
    {
        return (string) config('mcp-kit.tokens.default_preset', 'work');
    }
}
