@if ($this->tokens->isNotEmpty())
    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            @if ($this->canSeeAll)
                <flux:table.column>{{ __('Owner') }}</flux:table.column>
            @endif
            <flux:table.column>{{ __('Access') }}</flux:table.column>
            <flux:table.column>{{ __('Last used') }}</flux:table.column>
            <flux:table.column>{{ __('Expires') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->tokens as $token)
                <flux:table.row wire:key="token-{{ $token->id }}">
                    <flux:table.cell variant="strong">{{ $this->tokenLabel($token->name) }}</flux:table.cell>
                    @if ($this->canSeeAll)
                        <flux:table.cell>{{ $token->tokenable?->name ?? '—' }}</flux:table.cell>
                    @endif
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$this->scopeColor($token->abilities ?? [])">{{ $this->scopeLabel($token->abilities ?? []) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</flux:table.cell>
                    <flux:table.cell>{{ $token->expires_at?->toDateString() ?? __('Never') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="xs" variant="subtle" icon="trash" wire:click="revoke({{ $token->id }})" wire:confirm="{{ __('Revoke this token? Everything using it stops working immediately.') }}">
                            {{ __('Revoke') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
@endif
