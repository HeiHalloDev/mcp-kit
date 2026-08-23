<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Principal;
use Laravel\Sanctum\PersonalAccessToken;

final class TokenMinted
{
    public function __construct(
        public readonly PersonalAccessToken $token,
        public readonly ?Principal $by,
    ) {}
}
