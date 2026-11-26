<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Audit;

use Illuminate\Support\Str;

/**
 * Arguments and results as they may be stored: secrets and national id
 * numbers redacted, strings capped, big arrays counted.
 *
 * A key is redacted when its whole name matches, in any case and at any
 * depth: `ssn` is, `ssn_on_file` is not. Apps add their own names in
 * mcp-kit.redact_keys; the built-in list always applies.
 */
final class Sanitizer
{
    public const REDACTED_KEYS = [
        'password', 'token', 'secret', 'key', 'api_key', 'message', 'confirmation_token', 'bearer',
        // National id numbers: Flex's update_student takes the fødselsnummer as `ssn`.
        'ssn', 'personnummer', 'fodselsnummer', 'fødselsnummer', 'national_id',
    ];

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    public static function arguments(array $arguments, int $maxLength = 200, int $maxItems = 50): array
    {
        return self::clean($arguments, self::redactedKeys(), $maxLength, $maxItems);
    }

    /**
     * The built-in names and the app's own, lowercased.
     *
     * @return array<string, true>
     */
    public static function redactedKeys(): array
    {
        $extra = array_filter((array) config('mcp-kit.redact_keys', []), 'is_string');

        return array_fill_keys(array_map('mb_strtolower', [...self::REDACTED_KEYS, ...$extra]), true);
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     * @param  array<string, true>  $redacted
     * @return array<array-key, mixed>
     */
    private static function clean(array $arguments, array $redacted, int $maxLength, int $maxItems): array
    {
        $sanitized = [];

        foreach ($arguments as $key => $value) {
            $sanitized[$key] = match (true) {
                isset($redacted[mb_strtolower((string) $key)]) => '[REDACTED]',
                is_string($value) => Str::limit($value, $maxLength),
                is_array($value) => count($value) > $maxItems ? sprintf('[array:%d]', count($value)) : self::clean($value, $redacted, $maxLength, $maxItems),
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
