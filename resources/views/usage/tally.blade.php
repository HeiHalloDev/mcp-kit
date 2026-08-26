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

@if ($this->refused !== [])
    <flux:callout icon="exclamation-triangle" variant="warning">
        <flux:callout.heading>{{ __('An assistant could not record its work') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('working_on refused these, so the frame stayed blank. Somebody tried to say what the work was and the tool would not take it — the numbers above undercount by this much.') }}
            <ul class="mt-2 space-y-1">
                @foreach ($this->refused as $task)
                    <li class="text-sm">
                        {{ $task->purpose ?: __('Never named') }} —
                        {{ __(':count refused', ['count' => $task->refusals]) }}
                        <span class="opacity-60">({{ $task->name }}{{ $task->server ? ', '.$task->server : '' }}, {{ __(':count calls', ['count' => $task->calls]) }})</span>
                    </li>
                @endforeach
            </ul>
        </flux:callout.text>
    </flux:callout>
@endif
