<div class="w-full max-w-4xl space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('API tokens') }}</flux:heading>
        <flux:text class="mt-1 opacity-60">{{ __('Personal tokens for connecting Claude, Codex and other assistants. A token can never do more than your own account, and every call is logged in your name.') }}</flux:text>
    </div>

    @if ($plainTextToken)
        <flux:callout icon="key" variant="success">
            <flux:callout.heading>{{ __('Your new token — shown only once') }}</flux:callout.heading>
            <flux:callout.text>
                <flux:input :value="$plainTextToken" readonly copyable class="mt-2" />
                <span class="mt-2 block text-sm">{{ __('The connect snippets below are already filled in with this token.') }}</span>
            </flux:callout.text>
        </flux:callout>
    @endif

    @include('mcp-kit::tokens.list')
    @include('mcp-kit::tokens.form')
    @include('mcp-kit::tokens.connect')

    @if (config('mcp-kit.ui.enabled'))
        <livewire:mcp-kit.assistant-memory />
    @endif
</div>
