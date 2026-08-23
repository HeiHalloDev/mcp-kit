<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Audit;

use Illuminate\Support\Str;

/**
 * Arguments and results as they may be stored: secrets redacted, strings
 * capped, big arrays counted.
 */
final class Sanitizer
{
    public const REDACTED_KEYS = ['password', 'token', 'secret', 'key', 'api_key', 'message', 'confirmation_token', 'bearer'];

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    public static function arguments(array $arguments, int $maxLength = 200, int $maxItems = 50): array
    {
        $sanitized = [];

        foreach ($arguments as $key => $value) {
            $sanitized[$key] = match (true) {
                in_array(strtolower((string) $key), self::REDACTED_KEYS, true) => '[REDACTED]',
                is_string($value) => Str::limit($value, $maxLength),
                is_array($value) => count($value) > $maxItems ? sprintf('[array:%d]', count($value)) : self::arguments($value, $maxLength, $maxItems),
                is_scalar($value), $value === null => $value,
                default => gettype($value),
            };
        }

        return $sanitized;
    }

    /**
     * @param  array<array-key, mixed>  $result
     * @return array<array-key, mixed>
     */
    public static function result(array $result): array
    {
        return self::arguments($result, 200, 20);
    }
}
