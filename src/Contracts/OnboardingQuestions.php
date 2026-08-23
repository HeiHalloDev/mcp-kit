<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

use HeiHallo\McpKit\Describe\UserDescription;
use HeiHallo\McpKit\Memory\AssistantMemory;
use HeiHallo\McpKit\Onboarding\Question;

interface OnboardingQuestions
{
    /**
     * The questions still worth asking, in order, already capped.
     *
     * @param  list<string>  $abilities  the concrete abilities the token grants
     * @return list<Question>
     */
    public function missing(UserDescription $description, AssistantMemory $memory, array $abilities): array;
}
