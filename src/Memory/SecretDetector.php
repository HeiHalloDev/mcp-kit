<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Memory;

/**
 * Memory is for how a person works, never for credentials. Anything that
 * looks like one is refused before it is stored.
 */
final class SecretDetector
{
    public static function looksSecret(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (self::looksSecret($item) || (is_string($key) && preg_match('/passw|secret|token|api[_-]?key|bearer/i', $key))) {
                    return true;
                }
            }

            return false;
        }

        if (! is_string($value)) {
            return false;
        }

        return (bool) preg_match(
            '/(password|passord|secret|api[_-]?key|bearer\s+[a-z0-9]|sk-[a-z0-9]{12,}|\b\d+\|[A-Za-z0-9]{30,}\b|-----BEGIN)/i',
            $value,
        );
    }
}
