<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Livewire;

use HeiHallo\McpKit\Contracts\MemoryStore;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Events\MemoryUpdated;
use HeiHallo\McpKit\Memory\AssistantMemory as Memory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * What the assistant remembers about the signed-in person, with the three
 * things they may want: offer the intro again, never offer it, forget all.
 */
class AssistantMemory extends Component
{
    #[Computed]
    public function memory(): Memory
    {
        return app(MemoryStore::class)->get(auth()->user());
    }

    public function offerAgain(): void
    {
        $this->update($this->memory->with(['onboarding' => []]));
    }

    public function neverOffer(): void
    {
        $this->update($this->memory->with(['onboarding' => [...$this->memory->onboarding, 'declined_at' => now()->toIso8601String()]]));
    }

    public function forgetEverything(): void
    {
        $this->update(Memory::empty()->with(['onboarding' => $this->memory->onboarding]));
    }

    protected function update(Memory $after): void
    {
        $user = auth()->user();
        $before = $this->memory;

        app(MemoryStore::class)->put($user, $after);

        event(new MemoryUpdated($user, $before, $after, app(PrincipalResolver::class)->resolve($user), 'web'));

        unset($this->memory);
    }

    public function render(): View
    {
        return view('mcp-kit::livewire.assistant-memory');
    }
}
