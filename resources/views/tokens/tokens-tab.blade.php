{{--
    The API tokens tab of the Connect page, or the whole page with OAuth
    off: the token just minted, the list, the form, and how to connect a
    client with a token.
--}}
<div class="space-y-8">
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
</div>
