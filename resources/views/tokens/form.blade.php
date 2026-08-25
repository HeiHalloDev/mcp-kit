<div>
    @if ($this->presets === [])
        <flux:callout icon="lock-closed" variant="warning">
            <flux:callout.text>{{ __('Your account has no permissions to put in a token yet. Ask an administrator to grant you access to the areas you work in.') }}</flux:callout.text>
        </flux:callout>
    @elseif (! $showForm)
        <flux:button wire:click="toggleForm" icon="plus" variant="primary">{{ __('Add token') }}</flux:button>
    @else
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <form wire:submit="create" class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <flux:heading size="lg">{{ __('New token') }}</flux:heading>
                    <flux:button wire:click="toggleForm" type="button" size="sm" variant="subtle" icon="x-mark">{{ __('Cancel') }}</flux:button>
                </div>

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

                <flux:select wire:model="expiresDays" :label="__('Expires after')" :description="__('An expired token stops working; create a new one when that happens.')">
                    @foreach ($this->expiryOptions as $days => $label)
                        <flux:select.option value="{{ $days }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:button type="submit" variant="primary">{{ __('Create token') }}</flux:button>
            </form>
        </div>
    @endif
</div>
