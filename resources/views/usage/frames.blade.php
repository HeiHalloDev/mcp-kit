@php($frames = $this->frames)
@php($groups = [
    'short' => [__('Fell short'), __('Somebody came for something and did not get it. Read these against the gap list above.')],
    'hard' => [__('Worked, but should not have been that hard'), __('They got what they came for, so nothing else here flags these. The call count cannot see them either — only the assistant that did the work can say it fought the tools.')],
    'worked' => [__('Worked'), __('Went the way the tools expect.')],
    'unjudged' => [__('Nobody said how it went'), __('Opened by the first call and left. The calls are real; nothing is claimed about the outcome — unless it is marked refused, which means somebody tried.')],
])

<div class="space-y-8">
    <div>
        <flux:heading size="lg" level="2">{{ __('The work itself') }}</flux:heading>
        <flux:text class="mt-1 opacity-60">{{ __('The assistant\'s own words for what each piece of work was for. Evidence of intent, not a transcript.') }}</flux:text>
    </div>

    @foreach ($groups as $key => [$heading, $blurb])
        @continue ($frames[$key] === [])

        <div class="space-y-3">
            <div>
                <flux:heading size="sm" level="3">{{ $heading }} ({{ count($frames[$key]) }})</flux:heading>
                <flux:text class="mt-1 text-sm opacity-60">{{ $blurb }}</flux:text>
            </div>

            <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($frames[$key] as $task)
                    <div class="space-y-1 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium {{ $task->isUnnamed() ? 'opacity-50 italic' : '' }}">
                                {{ $task->isUnnamed() ? __('Never named') : $task->purpose }}
                            </span>

                            @if ($task->effort)
                                <flux:badge :color="$this->effortColor($task->effort)" size="sm">{{ $task->effort }}</flux:badge>
                            @endif

                            @if ($task->fellShort())
                                <flux:badge color="red" size="sm">{{ $task->outcome }}</flux:badge>
                            @endif

                            @if ($task->namingWasRefused())
                                <flux:badge color="red" size="sm" variant="solid">{{ __(':count refused', ['count' => $task->refusals]) }}</flux:badge>
                            @endif
                        </div>

                        @if ($task->result)
                            <flux:text class="text-sm">{{ $task->result }}</flux:text>
                        @endif

                        <flux:text class="text-xs opacity-60">
                            {{ $task->name }}
                            @if ($task->server)
                                · {{ $task->server }}
                            @endif
                            · {{ __(':count calls', ['count' => $task->calls]) }}
                            @if ($task->startedAt)
                                · {{ $task->startedAt->format('j M H:i') }}
                            @endif
                        </flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if ($this->tally['frames'] === 0)
        <flux:callout icon="information-circle">
            <flux:callout.heading>{{ __('Nothing recorded yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Either nobody has worked through the tools in this period, or recording is switched off for this app.') }}</flux:callout.text>
        </flux:callout>
    @endif
</div>
