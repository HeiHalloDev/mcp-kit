<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Onboarding;

use HeiHallo\McpKit\Contracts\OnboardingQuestions;
use HeiHallo\McpKit\Describe\UserDescription;
use HeiHallo\McpKit\Memory\AssistantMemory;

/**
 * Asks only for what is missing, in this order: a typical day (routines
 * empty), hand-offs (empty), tone (the token writes to customers and no
 * language/tone preference is known), role (the app does not know it and
 * memory has none), team (the app does not know it). Capped by
 * mcp-kit.onboarding.max_questions.
 */
class DefaultQuestions implements OnboardingQuestions
{
    public function missing(UserDescription $description, AssistantMemory $memory, array $abilities): array
    {
        $questions = [];

        if ($memory->routines === []) {
            $questions[] = new Question('day', 'What does a normal day look like for you here: which things do you do most, in which order?', 'routines');
        }

        if ($memory->handoffs === []) {
            $questions[] = new Question('handoffs', 'Who do you hand things to, and when? (For example: anything about invoices goes to X.)', 'handoffs');
        }

        $customerFacing = array_map('strval', (array) config('mcp-kit.onboarding.customer_facing_abilities', []));
        $toneKnown = isset($memory->preferences['language']) || isset($memory->preferences['tone']);

        if (! $toneKnown && array_intersect($customerFacing, $abilities) !== []) {
            $questions[] = new Question('tone', 'When something goes out to customers, which language and tone should it have?', 'preferences.language / preferences.tone');
        }

        if (! $description->knowsRole() && $memory->role === null) {
            $questions[] = new Question('role', 'What is your role, in your own words?', 'role');
        }

        if (! $description->knowsTeam() && $memory->team === null) {
            $questions[] = new Question('team', 'Which team or area do you belong to?', 'team');
        }

        return array_slice($questions, 0, max(0, (int) config('mcp-kit.onboarding.max_questions', 3)));
    }
}
