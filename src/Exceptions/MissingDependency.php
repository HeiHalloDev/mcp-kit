<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Exceptions;

use RuntimeException;

final class MissingDependency extends RuntimeException
{
    public static function for(string $package, string $feature): self
    {
        return new self("{$feature} needs {$package}. Run `composer require {$package}`, or turn the feature off.");
    }
}
