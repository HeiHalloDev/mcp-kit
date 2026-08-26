@php($tally = $this->tally)

<div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:text class="text-xs uppercase tracking-wide opacity-60">{{ __('Pieces of work') }}</flux:text>
        <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $tally['frames'] }}</div>
    </div>

    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:text class="text-xs uppercase tracking-wide opacity-60">{{ __('Tool calls') }}</flux:text>
        <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $tally['calls'] }}</div>
    </div>

    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:text class="text-xs uppercase tracking-wide opacity-60">{{ __('Given a purpose') }}</flux:text>
        <div class="mt-1 text-2xl font-semibold tabular-nums">
            {{ $tally['share'] === null ? '—' : $tally['share'].'%' }}
        </div>
        <flux:text class="mt-1 text-xs opacity-60">{{ __('A frame opens by itself; naming it is the part somebody has to do.') }}</flux:text>
    </div>
</div>

@if ($tally['capped'])
    <flux:text class="text-sm opacity-60">{{ __('Showing the most recent frames only — there are more in this period than this page reads.') }}</flux:text>
@endif
