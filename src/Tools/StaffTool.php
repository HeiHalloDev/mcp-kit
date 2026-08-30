<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools;

use HeiHallo\McpKit\Tools\Concerns\AcceptsUploads;
use HeiHallo\McpKit\Tools\Concerns\ChecksAbilities;
use HeiHallo\McpKit\Tools\Concerns\ConfirmsWrites;
use HeiHallo\McpKit\Tools\Concerns\DeclaresInputSchema;
use HeiHallo\McpKit\Tools\Concerns\LinksToAdmin;
use HeiHallo\McpKit\Tools\Concerns\PagesResults;
use HeiHallo\McpKit\Tools\Concerns\ResolvesPrincipal;
use Laravel\Mcp\Server\Tool;

/**
 * Every concern in one base class. Existing tools may keep extending
 * Laravel\Mcp\Server\Tool and `use` only what they need.
 */
abstract class StaffTool extends Tool
{
    use AcceptsUploads;
    use ChecksAbilities;
    use ConfirmsWrites;
    use DeclaresInputSchema;
    use LinksToAdmin;
    use PagesResults;
    use ResolvesPrincipal;
}
