<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Testing;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The actors the guard tests mint tokens for, registered once in
 * tests/Pest.php through Guards::actors(...).
 */
final class Actors
{
    /** @var array<string, Closure|null> */
    private static array $factories = [];

    /**
     * @param  (Closure(): Authenticatable)|null  $staff  an ordinary staff user with a few permissions
     * @param  (Closure(): Authenticatable)|null  $privileged  a user who may hold wildcards and explicit-only abilities
     * @param  (Closure(): Authenticatable)|null  $blocked  a blocked user
     * @param  (Closure(): Authenticatable)|null  $serviceClient  an active service client
     */
    public static function register(?Closure $staff, ?Closure $privileged, ?Closure $blocked, ?Closure $serviceClient): void
    {
        self::$factories = compact('staff', 'privileged', 'blocked', 'serviceClient');
    }

    public static function has(string $actor): bool
    {
        return (self::$factories[$actor] ?? null) !== null;
    }

    public static function make(string $actor): Authenticatable
    {
        $factory = self::$factories[$actor] ?? null;

        if ($factory === null) {
            throw new \RuntimeException("No '{$actor}' actor registered — call Guards::actors(...) in tests/Pest.php.");
        }

        return $factory();
    }

    public static function clear(): void
    {
        self::$factories = [];
    }
}
