<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Audit\CallRecord;

final class ToolCallRecorded
{
    public function __construct(public readonly CallRecord $record) {}
}
