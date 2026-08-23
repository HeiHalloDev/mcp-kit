<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Prompts;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\MemoryStore;
use HeiHallo\McpKit\Contracts\OnboardingQuestions;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\SuggestsTasks;
use HeiHallo\McpKit\Contracts\UserDescriber;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * A short conversation about how the person works, asked only for what the
 * app does not already know, saved only when they say so.
 */
class GettingStartedPrompt extends Prompt
{
    protected string $name = 'getting_started';

    protected string $description = 'A short intro: learn how this person works (only what is not already known), suggest three things to try, and save what they confirm with remember_about_me. Offer once; never push.';

    public function arguments(): array
    {
        return [
            new Argument(name: 'focus', description: 'Optional: what the person wants to get done today, to steer the suggestions.', required: false),
        ];
    }

    public function handle(Request $request): Response
    {
        $tokenable = $request->user();
        $principal = $tokenable ? app(PrincipalResolver::class)->resolve($tokenable) : null;

        if ($principal === null) {
            return Response::error('Authentication required.');
        }

        if ($principal->isService()) {
            return Response::error('getting_started is for people: a service client has no profile.');
        }

        $catalogue = app(AbilityCatalogue::class);
        $description = app(UserDescriber::class)->describe($principal);
        $memory = app(MemoryStore::class)->get($principal->tokenable);
        $abilities = $catalogue->expand($principal->abilities());

        $known = array_filter([
            'name' => $description->name,
            'role' => $description->role ?? $memory->role,
            'team' => $description->team ?? $memory->team,
            'inboxes' => $description->inboxes === [] ? null : implode(', ', $description->inboxes),
            'permissions' => $description->permissions === [] ? null : implode(', ', $description->permissions),
            'routines' => $memory->routines === [] ? null : implode('; ', $memory->routines),
            'handoffs' => $memory->handoffs === [] ? null : implode('; ', $memory->handoffs),
            'preferences' => $memory->preferences === [] ? null : json_encode($memory->preferences, JSON_UNESCAPED_UNICODE),
        ]);

        $text = view('mcp-kit::prompts.getting-started', [
            'principal' => $principal,
            'known' => $known,
            'questions' => app(OnboardingQuestions::class)->missing($description, $memory, $abilities),
            'suggestions' => app(SuggestsTasks::class)->suggestions($principal, $abilities),
            'focus' => trim((string) $request->get('focus', '')),
            'scheme' => config('mcp-kit.scheme', 'app'),
            'canChange' => array_values(array_filter($abilities, fn (string $a): bool => $catalogue->isWrite($a))) !== [],
        ])->render();

        return Response::text($text);
    }
}
