<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Principal;

interface SuggestsTasks
{
    /**
     * Things to try, filtered to what the token can actually do.
     *
     * @param  list<string>  $abilities
     * @return list<string>
     */
    public function suggestions(Principal $principal, array $abilities): array;
}
