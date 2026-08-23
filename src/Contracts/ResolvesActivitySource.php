<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Principal;

interface ResolvesActivitySource
{
    /**
     * The product an activity row belongs to (flex.example, the CRM, …), or
     * null to leave the column alone.
     */
    public function source(?Principal $principal): ?string;
}
