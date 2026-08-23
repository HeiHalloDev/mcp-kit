<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Audit\WriteRecord;

final class WriteConfirmed
{
    public function __construct(public readonly WriteRecord $record) {}
}
