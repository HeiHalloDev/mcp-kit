<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\OAuth;

/**
 * Which redirect URIs a client may register, and whether one it sends later
 * is one it registered. https on an allowed host, or loopback for the CLIs.
 */
class RedirectUris
{
    public static function allowed(string $uri): bool
    {
        $parts = parse_url($uri);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['fragment']) || isset($parts['user'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        if (static::isLoopbackHost($host)) {
            return (bool) config('mcp-kit.oauth.loopback', true) && in_array($parts['scheme'], ['http', 'https'], true);
        }

        if ($parts['scheme'] !== 'https') {
            return false;
        }

        foreach ((array) config('mcp-kit.oauth.redirect_hosts', []) as $allowed) {
            $allowed = strtolower((string) $allowed);

            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exact match, except that a loopback redirect may come back on any
     * port (RFC 8252 §7.3): the CLIs listen wherever a port is free.
     *
     * @param  list<string>  $registered
     */
    public static function matches(string $uri, array $registered): bool
    {
        if (in_array($uri, $registered, true)) {
            return true;
        }

        $sent = parse_url($uri);

        if (! is_array($sent) || ! isset($sent['host']) || ! static::isLoopbackHost(strtolower($sent['host']))) {
            return false;
        }

        foreach ($registered as $candidate) {
            $known = parse_url($candidate);

            if (is_array($known)
                && ($known['scheme'] ?? null) === ($sent['scheme'] ?? null)
                && strtolower($known['host'] ?? '') === strtolower($sent['host'])
                && ($known['path'] ?? '') === ($sent['path'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    public static function isLoopbackHost(string $host): bool
    {
        return in_array(trim($host, '[]'), ['127.0.0.1', 'localhost', '::1'], true);
    }
}
