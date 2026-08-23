<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;

final class OnboardingCompleted
{
    public function __construct(public readonly Authenticatable $user) {}
}
