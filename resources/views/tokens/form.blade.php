<div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
    @if ($this->presets === [])
        <flux:callout icon="lock-closed" variant="warning">
            <flux:callout.text>{{ __('Your account has no permissions to put in a token yet. Ask an administrator to grant you access to the areas you work in.') }}</flux:callout.text>
        </flux:callout>
    @else
        <form wire:submit="create" class="space-y-4">
            <flux:heading size="lg">{{ __('New token') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Claude Code on the laptop')" :description="__('Name it after where it lives, so you know what stops working if you revoke it.')" />
            <flux:radio.group wire:model.live="preset" :label="__('Access')" :description="__('Presets are filtered by your own permissions — you only get what you already have in the UI.')">
                @foreach ($this->presets as $key => $option)
                    <flux:radio value="{{ $key }}" :label="$option['label']" :description="$option['description']" />
                @endforeach
            </flux:radio.group>

            @if ($this->extraOptions !== [])
                <flux:checkbox.group wire:model.live="extras" :label="__('Extended (privileged only)')" :description="__('Never granted by a wildcard — has to be chosen here.')">
                    @foreach ($this->extraOptions as $ability => $description)
                        <flux:checkbox :value="$ability" :label="$ability" :description="$description" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <flux:input type="number" wire:model="expiresDays" :label="__('Expires after (days)')" min="1" :max="$maxDays" :description="$maxDays ? __('At most :max days.', ['max' => $maxDays]) : ''" />

            <flux:button type="submit" variant="primary">{{ __('Create token') }}</flux:button>
        </form>
    @endif
</div>
