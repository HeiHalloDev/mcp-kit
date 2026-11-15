<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="lg" level="2">{{ __('What people could not get') }}</flux:heading>
            <flux:text class="mt-1 opacity-60">{{ __('Reported through report_gap. Most-wanted first; anything blocking is at the top.') }}</flux:text>
        </div>

        <flux:radio.group wire:model.live="gapScope" variant="segmented" size="sm">
            <flux:radio value="live" :label="__('Open')" />
            <flux:radio value="closed" :label="__('Decided')" />
            <flux:radio value="all" :label="__('All')" />
        </flux:radio.group>
    </div>

    @forelse ($this->gaps as $gap)
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="sm" level="3">{{ $gap->title }}</flux:heading>

                        @if ($gap->blocking)
                            <flux:badge color="red" size="sm">{{ __('Blocking') }}</flux:badge>
                        @endif

                        <flux:badge :color="$this->statusColor($gap->status)" size="sm">{{ $gap->status }}</flux:badge>

                        @if ($gap->reports > 1)
                            <flux:badge color="zinc" size="sm">{{ __(':count reports', ['count' => $gap->reports]) }}</flux:badge>
                        @endif
                    </div>

                    <flux:text class="mt-2">{{ $gap->need }}</flux:text>
                    <flux:text class="mt-1 opacity-60">{{ __('Missing:') }} {{ $gap->missing }}</flux:text>

                    <flux:text class="mt-2 text-xs opacity-60">
                        {{ collect($gap->reporters)->pluck('name')->unique()->join(', ') }}
                        @if ($gap->server)
                            · {{ $gap->server }}
                        @endif
                        @if ($gap->tool)
                            · {{ $gap->tool }}
                        @endif
                    </flux:text>

                    @if ($gap->resolution)
                        <flux:callout class="mt-3" variant="secondary">
                            <flux:callout.text>
                                {{ $gap->resolution }}
                                <span class="mt-1 block text-xs opacity-60">{{ $gap->resolvedBy }}</span>
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    @if ($gap->followUps !== [])
                        <div class="mt-3 space-y-2 border-l-2 border-zinc-200 pl-3 dark:border-zinc-700">
                            <flux:text class="text-xs font-medium uppercase tracking-wide opacity-60">{{ __('Follow-ups') }}</flux:text>

                            @foreach ($gap->followUps as $followUp)
                                <div wire:key="follow-up-{{ $gap->id }}-{{ $loop->index }}">
                                    <flux:text class="whitespace-pre-line">{{ $followUp['text'] }}</flux:text>
                                    <flux:text class="text-xs opacity-60">{{ $followUp['name'] }} · {{ \HeiHallo\McpKit\Gaps\Gap::stamp($followUp['at']) }}</flux:text>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($this->settling !== (string) $gap->id && $this->followingUp !== (string) $gap->id)
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="subtle" wire:click="startFollowingUp('{{ $gap->id }}')">
                            {{ __('Follow up') }}
                        </flux:button>

                        @if (in_array($gap->status, ['open', 'planned'], true))
                            <flux:button size="sm" variant="subtle" wire:click="startSettling('{{ $gap->id }}')">
                                {{ __('Decide') }}
                            </flux:button>
                        @endif
                    </div>
                @endif
            </div>

            @if ($this->followingUp === (string) $gap->id)
                <div class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:field>
                        <flux:label>{{ __('Follow up') }}</flux:label>
                        <flux:description>{{ __('A question to the people who reported it, or what you found so far. It reaches them on their next tool call, and the status stays as it is.') }}</flux:description>
                        <flux:textarea wire:model="followUp" rows="3" />
                        <flux:error name="followUp" />
                    </flux:field>

                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="primary" wire:click="writeFollowUp">{{ __('Add follow-up') }}</flux:button>
                        <flux:button size="sm" variant="subtle" wire:click="cancelFollowingUp">{{ __('Cancel') }}</flux:button>
                    </div>
                </div>
            @endif

            @if ($this->settling === (string) $gap->id)
                <div class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:field>
                        <flux:label>{{ __('What was decided') }}</flux:label>
                        <flux:description>{{ __('Everybody who reported this reads it the next time they ask. One line.') }}</flux:description>
                        <flux:input wire:model="resolution" :placeholder="__('Built as list_module_bookings, out on Friday.')" />
                        <flux:error name="resolution" />
                    </flux:field>

                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="primary" wire:click="settle('done')">{{ __('Built it') }}</flux:button>
                        <flux:button size="sm" wire:click="settle('planned')">{{ __('Planned') }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="settle('declined')">{{ __('Not doing it') }}</flux:button>
                        <flux:button size="sm" variant="subtle" wire:click="cancelSettling">{{ __('Cancel') }}</flux:button>
                    </div>
                </div>
            @endif
        </div>
    @empty
        <flux:text class="opacity-60">{{ __('Nothing reported in this view.') }}</flux:text>
    @endforelse
</div>
