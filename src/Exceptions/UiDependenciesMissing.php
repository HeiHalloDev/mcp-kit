<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Exceptions;

use RuntimeException;

final class UiDependenciesMissing extends RuntimeException
{
    public static function create(): self
    {
        return new self(
            'The mcp-kit UI needs livewire/livewire and livewire/flux (the tokens page also uses Flux Pro tables and tabs). '.
            'Run `composer require livewire/livewire livewire/flux`, or set mcp-kit.ui.enabled=false.'
        );
    }
}
