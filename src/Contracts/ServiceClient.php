<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

interface ServiceClient
{
    public function isActive(): bool;

    public function touchLastUsed(): void;

    public function displayName(): string;
}
