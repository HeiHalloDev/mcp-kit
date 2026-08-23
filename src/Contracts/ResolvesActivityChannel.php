<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Enums\ActivityChannel;

interface ResolvesActivityChannel
{
    public function channel(): ActivityChannel;
}
