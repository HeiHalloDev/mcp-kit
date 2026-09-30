<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools;

use HeiHallo\McpKit\Tools\Concerns\ChecksAbilities;
use Laravel\Mcp\Request;

/**
 * Asks the same question a tool asks at the top of handle(), without the
 * side effects of a refusal: no denial is recorded and no event fires.
 * Used to decide what a caller is shown, never whether a call may run.
 */
final class AbilityProbe
{
    use ChecksAbilities;

    public function allows(Request $request, string $ability): bool
    {
        return $this->computeAbilityDenial($request, $ability) === null;
    }
}
