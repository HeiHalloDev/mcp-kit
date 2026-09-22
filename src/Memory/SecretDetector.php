<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Memory;

/**
 * Memory is for how a person works, never for credentials. Anything that
 * looks like one is refused before it is stored.
 *
 * Talking about a credential is not carrying one. The first rules here
 * matched the word alone, so "the bearer token path is unusable for
 * connector staff" — a report about tokens, containing none — was refused,
 * and so was every gap about resetting a password. A credential has a
 * shape: a name with a value after it, or a string that is itself the
 * value. Nothing else is guessed at.
 */
final class SecretDetector
{
    /** A credential word, and whatever follows it: `password: hunter2`, `passord hunter2`. */
    private const NAMED = '/(?:passw(?:or)?d|passord|secret|api[_\-\s]?key|access[_\-\s]?token|auth[_\-\s]?token)\s*(=|:|\bis\b|\ber\b)?\s*["\x27]?([^\s"\x27]{6,})/i';

    /** Strings that are a credential whatever surrounds them. */
    private const SHAPED = [
        '/\bbearer\s+[A-Za-z0-9._\-]{16,}/i',      // an Authorization header, pasted
        '/\bsk-[A-Za-z0-9]{12,}/',                  // OpenAI and friends
        '/\b\d+\|[A-Za-z0-9]{30,}\b/',              // a Sanctum personal access token
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',     // a key file
        '/\bgh[pousr]_[A-Za-z0-9]{20,}/',           // a GitHub token
        '/\bxox[baprs]-[A-Za-z0-9\-]{10,}/',        // a Slack token
    ];

    public static function looksSecret(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (self::looksSecret($item) || (is_string($key) && self::fieldCarriesOne($key, $item))) {
                    return true;
                }
            }

            return false;
        }

        if (! is_string($value)) {
            return false;
        }

        if (self::namedCredential($value)) {
            return true;
        }

        foreach (self::SHAPED as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A credential word followed by something that could be the credential.
     *
     * The delimiter settles it on its own — nobody writes "password:" in
     * front of prose. Without one, the value has to look like a value:
     * "log in with password hunter2" is a credential, "no way to reset a
     * password from here" is a sentence about one.
     */
    private static function namedCredential(string $value): bool
    {
        if (! preg_match_all(self::NAMED, $value, $matches, PREG_SET_ORDER)) {
            return false;
        }

        foreach ($matches as $match) {
            $delimiter = trim((string) ($match[1] ?? ''));
            $candidate = (string) ($match[2] ?? '');

            if ($delimiter !== '') {
                return true;
            }

            $mixed = preg_match('/[A-Za-z]/', $candidate) && preg_match('/\d/', $candidate);

            if ($mixed || mb_strlen($candidate) >= 16 || preg_match('/[^A-Za-z0-9.,;!?()\-]/', $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A field named for a credential, holding something that could be one:
     * one unbroken run of characters. `token: "the bearer token path"` is
     * prose in a badly named field, not a credential.
     */
    private static function fieldCarriesOne(string $key, mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/passw|secret|token|api[_-]?key|bearer/i', $key)) {
            return false;
        }

        $value = trim($value);

        return $value !== '' && ! str_contains($value, ' ') && mb_strlen($value) >= 8;
    }
}
