<div class="w-full max-w-5xl space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('What the tools are used for') }}</flux:heading>
        <flux:text class="mt-1 opacity-60">{{ __('What people came here to do, whether they got it, and what they needed that this app cannot do yet. A record of colleagues\' work — read it as evidence, not as a scoreboard.') }}</flux:text>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="days" :label="__('Period')" class="max-w-48">
            @foreach ($this->dayOptions as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="server" :label="__('Server')" class="max-w-48">
            @foreach ($this->serverOptions as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @include('mcp-kit::usage.tally')
    @include('mcp-kit::usage.gaps')
    @include('mcp-kit::usage.frames')
</div>
