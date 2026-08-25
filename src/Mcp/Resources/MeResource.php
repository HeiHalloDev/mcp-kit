<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Resources;

use HeiHallo\McpKit\Activity\RecentActivity;
use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\MemoryStore;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\UserDescriber;
use HeiHallo\McpKit\Events\OnboardingOffered;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Memory\AssistantMemory;
use HeiHallo\McpKit\Principal;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Throwable;

/**
 * Who the assistant is talking to: the person, where they work, what the
 * token may do, how they usually work, what was remembered, what they did
 * lately. A hint, not a mode.
 */
class MeResource extends Resource
{
    protected string $name = 'me';

    protected string $description = 'Who you are talking to: role, team, what this token may do, how the person usually works, what has been remembered. Read at the start of a session. A hint, never a constraint.';

    protected string $mimeType = 'text/markdown';

    public function uri(): string
    {
        return config('mcp-kit.scheme', 'app').'://me';
    }

    public function handle(Request $request): Response
    {
        $tokenable = $request->user();
        $principal = $tokenable ? app(PrincipalResolver::class)->resolve($tokenable) : null;

        if ($principal === null) {
            return Response::error('Authentication required.');
        }

        return Response::text(static::render($principal));
    }

    public static function render(Principal $principal): string
    {
        if ($principal->isService()) {
            return view('mcp-kit::resources.me', [
                'principal' => $principal,
                'service' => true,
                'scheme' => config('mcp-kit.scheme', 'app'),
            ])->render();
        }

        $catalogue = app(AbilityCatalogue::class);
        $store = app(MemoryStore::class);
        $memory = $store->get($principal->tokenable);
        $granted = $catalogue->expand($principal->abilities());
        $invite = false;

        // The one-time invitation: shown once, then never pushed again.
        if ($memory->isEmpty() && ! $memory->onboardingOffered() && ! $memory->onboardingDeclined()) {
            $invite = true;
            $store->put($principal->tokenable, $memory->with([
                'onboarding' => [...$memory->onboarding, 'offered_at' => now()->toIso8601String()],
            ]));

            event(new OnboardingOffered($principal->tokenable));
        }

        return view('mcp-kit::resources.me', [
            'principal' => $principal,
            'service' => false,
            'settled' => static::settledGaps($principal),
            'recording' => (bool) config('mcp-kit.learning.enabled', false),
            'recordingDays' => (int) (config('mcp-kit.learning.retain_days') ?? config('mcp-kit.activity.retain_days', 90)),
            'scheme' => config('mcp-kit.scheme', 'app'),
            'description' => app(UserDescriber::class)->describe($principal),
            'memory' => $memory,
            'can' => array_values(array_filter($granted, fn (string $a): bool => ! $catalogue->isWrite($a))),
            'canChange' => array_values(array_filter($granted, fn (string $a): bool => $catalogue->isWrite($a))),
            'descriptions' => array_combine($granted, array_map(fn (string $a): string => (string) ($catalogue->description($a) ?? $a), $granted)),
            'servers' => $catalogue->serversFor($principal->abilities()),
            'recent' => app(RecentActivity::class)->for($principal->tokenable),
            'invite' => $invite,
            'memoryUrl' => config('mcp-kit.ui.memory_url'),
            'expiresAt' => $principal->tokenExpiresAt(),
        ])->render();
    }

    /**
     * Answers waiting for this person: gaps they reported that have since
     * been built, planned or turned down. Reading them here is what marks
     * them heard, so each answer is given once and never nags.
     *
     * @return list<Gap>
     */
    protected static function settledGaps(Principal $principal): array
    {
        if (! config('mcp-kit.gaps.enabled', true)) {
            return [];
        }

        try {
            $store = app(GapStore::class);
            $settled = $store->settledFor($principal->name);

            foreach ($settled as $gap) {
                $store->markHeard($gap, $principal->name);
            }

            return $settled;
        } catch (Throwable $e) {
            // An app that has not migrated yet still gets its profile.
            report($e);

            return [];
        }
    }

    public static function memoryFor(Principal $principal): AssistantMemory
    {
        return app(MemoryStore::class)->get($principal->tokenable);
    }
}
