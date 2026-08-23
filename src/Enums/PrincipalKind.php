<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Enums;

enum PrincipalKind: string
{
    case Person = 'person';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Person',
            self::Service => 'Service client',
        };
    }
}
