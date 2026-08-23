<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Activity;

use HeiHallo\McpKit\Audit\McpCallContext;
use HeiHallo\McpKit\Contracts\ResolvesActivityChannel;
use HeiHallo\McpKit\Enums\ActivityChannel;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * An active tool call → its channel (mcp, or chat for an in-app agent); a
 * request with a personal token → api; a
 * request with a session user → web; console → cli; otherwise system.
 */
class DefaultChannelResolver implements ResolvesActivityChannel
{
    public function __construct(protected Application $app, protected McpCallContext $context) {}

    public function channel(): ActivityChannel
    {
        if ($this->context->isActive()) {
            return $this->context->channel();
        }

        if ($this->app->runningInConsole()) {
            return ActivityChannel::Cli;
        }

        try {
            $user = $this->app->make('auth')->user();
        } catch (Throwable) {
            return ActivityChannel::System;
        }

        if ($user === null) {
            return ActivityChannel::System;
        }

        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken) {
            return ActivityChannel::Api;
        }

        return ActivityChannel::Web;
    }
}
