<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\OAuth;

/**
 * RFC 7636 with S256 only. "plain" proves nothing an eavesdropper on the
 * redirect could not also show, so it is not accepted.
 */
class Pkce
{
    public static function validChallenge(string $challenge): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge);
    }

    public static function verifies(string $verifier, string $challenge): bool
    {
        if (! preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier)) {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }
}
