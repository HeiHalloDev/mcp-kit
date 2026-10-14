@if (config('mcp-kit.messages.enabled', true))
<div class="space-y-4">
    <div>
        <flux:heading size="lg" level="2">{{ __('Messages') }}</flux:heading>
        <flux:text class="mt-1 opacity-60">{{ __('Reaches people through their own assistant, on top of their next tool results, until it confirms it told them. Colleagues can message each other the same way with send_message.') }}</flux:text>
    </div>

    <form wire:submit="sendMessage" class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:textarea wire:model="messageBody" :label="__('Message')" rows="3" />
        <div class="flex flex-wrap items-end gap-3">
            <flux:input wire:model="messageTo" :label="__('To')" :placeholder="__('Names or e-mails, comma-separated')" class="min-w-64 flex-1" :disabled="$messageEveryone" />
            <flux:checkbox wire:model.live="messageEveryone" :label="__('Everyone using the tools')" />
            <flux:button type="submit" variant="primary" size="sm">{{ __('Send') }}</flux:button>
        </div>
        <flux:error name="messageTo" />
        <flux:error name="messageBody" />
    </form>

    @foreach ($this->sentMessages as $row)
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="message-{{ $row['message']->id }}">
            <flux:text>{{ $row['message']->body }}</flux:text>
            <flux:text class="mt-2 text-xs opacity-60">
                {{ $row['message']->sent_by }} · {{ $row['message']->created_at?->diffForHumans() }} ·
                {{ $row['message']->to_everyone ? __('Everyone') : ($row['message']->to_ability ?? collect($row['message']->to_people)->join(', ')) }}
            </flux:text>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach ($row['delivered'] as $person)
                    <flux:badge color="green" size="sm">{{ $person }}</flux:badge>
                @endforeach
                @foreach ($row['shown'] as $person)
                    <flux:badge color="amber" size="sm">{{ $person }} · {{ __('not confirmed') }}</flux:badge>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endif
