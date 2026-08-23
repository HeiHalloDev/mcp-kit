<div class="space-y-4">
    <div>
        <flux:heading size="lg">{{ __('What the assistant remembers about you') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('Saved only when you confirmed it in a conversation. It helps the assistant with defaults; it never limits what you can ask.') }}</flux:text>
    </div>

    @if ($this->memory->isEmpty())
        <flux:text class="text-sm">{{ __('Nothing remembered yet.') }}</flux:text>
    @else
        <dl class="space-y-2 text-sm">
            @if ($this->memory->role)
                <div><dt class="font-medium">{{ __('Role') }}</dt><dd>{{ $this->memory->role }}</dd></div>
            @endif
            @if ($this->memory->team)
                <div><dt class="font-medium">{{ __('Team') }}</dt><dd>{{ $this->memory->team }}</dd></div>
            @endif
            @if ($this->memory->routines !== [])
                <div><dt class="font-medium">{{ __('Usually') }}</dt><dd><ul class="list-disc pl-5">@foreach ($this->memory->routines as $item)<li>{{ $item }}</li>@endforeach</ul></dd></div>
            @endif
            @if ($this->memory->handoffs !== [])
                <div><dt class="font-medium">{{ __('Hands off') }}</dt><dd><ul class="list-disc pl-5">@foreach ($this->memory->handoffs as $item)<li>{{ $item }}</li>@endforeach</ul></dd></div>
            @endif
            @if ($this->memory->preferences !== [])
                <div><dt class="font-medium">{{ __('Preferences') }}</dt><dd>@foreach ($this->memory->preferences as $key => $value)<span class="mr-3">{{ $key }}: {{ is_bool($value) ? ($value ? 'yes' : 'no') : $value }}</span>@endforeach</dd></div>
            @endif
            @if ($this->memory->notes !== [])
                <div><dt class="font-medium">{{ __('Notes') }}</dt><dd><ul class="list-disc pl-5">@foreach ($this->memory->notes as $note)<li>{{ $note['text'] }}</li>@endforeach</ul></dd></div>
            @endif
        </dl>
    @endif

    <div class="flex flex-wrap gap-2">
        @if ($this->memory->onboardingDeclined() || $this->memory->onboardingOffered())
            <flux:button size="sm" variant="subtle" wire:click="offerAgain">{{ __('Offer the intro again') }}</flux:button>
        @endif
        @unless ($this->memory->onboardingDeclined())
            <flux:button size="sm" variant="subtle" wire:click="neverOffer">{{ __('Never offer the intro') }}</flux:button>
        @endunless
        @unless ($this->memory->isEmpty())
            <flux:button size="sm" variant="danger" wire:click="forgetEverything" wire:confirm="{{ __('Forget everything the assistant remembers about you?') }}">{{ __('Forget everything') }}</flux:button>
        @endunless
    </div>
</div>
