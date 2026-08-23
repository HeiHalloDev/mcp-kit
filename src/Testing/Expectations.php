<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Testing;

use Laravel\Mcp\Server\Testing\TestResponse;

/**
 * Pest expectations for laravel/mcp test responses:
 * expect($response)->toBePreview(), ->toHaveExecuted(), ->toDenyAbility('x:y:read').
 */
final class Expectations
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || ! function_exists('expect')) {
            return;
        }

        self::$registered = true;

        expect()->extend('toBePreview', function (?string $action = null) {
            /** @var TestResponse $response */
            $response = $this->value;
            $response->assertOk()->assertSee('"preview":true');

            if ($action !== null) {
                $response->assertSee('"action":'.json_encode($action, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            return $this;
        });

        expect()->extend('toHaveExecuted', function (?string $action = null) {
            /** @var TestResponse $response */
            $response = $this->value;
            $response->assertOk()->assertSee('"success":true')->assertDontSee('"preview":true');

            if ($action !== null) {
                $response->assertSee('"action":'.json_encode($action, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            return $this;
        });

        expect()->extend('toDenyAbility', function (string $ability) {
            /** @var TestResponse $response */
            $response = $this->value;
            $response->assertSee("Required ability: {$ability}");

            return $this;
        });
    }
}
