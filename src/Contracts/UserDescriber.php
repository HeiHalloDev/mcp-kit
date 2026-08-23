<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Describe\UserDescription;
use HeiHallo\McpKit\Principal;

interface UserDescriber
{
    public function describe(Principal $principal): UserDescription;
}
