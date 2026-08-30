<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Events;

use HeiHallo\McpKit\Models\Upload;
use HeiHallo\McpKit\Principal;

final class FileStaged
{
    public function __construct(
        public readonly Upload $upload,
        public readonly Principal $by,
    ) {}
}
