<div class="w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Connected apps') }}</flux:heading>
        <flux:text class="mt-1 opacity-60">{{ __('AI assistants you have signed in to with this account. Each can do what you allowed when you connected it, and never more than your own account may. Disconnect one and it loses access at once.') }}</flux:text>
    </div>

    @forelse ($this->grants as $grant)
        <div wire:key="grant-{{ $grant->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <flux:heading size="lg">{{ $grant->client?->name ?? __('Unknown app') }}</flux:heading>
                    <flux:text class="text-sm opacity-60">
                        {{ __('Connected :date', ['date' => $grant->created_at->diffForHumans()]) }}
                        @if ($grant->last_used_at)
                            · {{ __('last active :date', ['date' => $grant->last_used_at->diffForHumans()]) }}
                        @endif
                    </flux:text>
                </div>

                <flux:button size="sm" variant="danger" wire:click="disconnect({{ $grant->id }})" wire:confirm="{{ __('Disconnect :app? It loses access immediately.', ['app' => $grant->client?->name]) }}">
                    {{ __('Disconnect') }}
                </flux:button>
            </div>

            <ul class="mt-3 space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                @foreach ((array) $grant->abilities as $ability)
                    <li>{{ $this->describe((string) $ability) }}</li>
                @endforeach
            </ul>
        </div>
    @empty
        <flux:text>{{ __('No assistants are connected to your account.') }}</flux:text>
    @endforelse
</div>
