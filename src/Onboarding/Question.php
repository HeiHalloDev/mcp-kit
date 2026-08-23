<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Onboarding;

final class Question
{
    public function __construct(
        public readonly string $key,
        public readonly string $text,
        public readonly string $saveAs,
    ) {}
}
