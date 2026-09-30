{{--
    Connect AI: one page for connecting an assistant. With OAuth on it opens
    on signing in with a URL, and tokens sit behind the second segment; the
    segmented switch looks unlike the client tabs inside each half, so the
    two levels are not mistaken for one another. With OAuth off there is
    nothing to switch between, and the page is the token half with what to
    ask under it.
--}}
@php($oauth = (bool) config('mcp-kit.oauth.enabled'))

<div class="w-full max-w-4xl space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Connect AI') }}</flux:heading>
        <flux:text class="mt-1 opacity-60">{{ __('Connect Claude, Codex or another assistant to your account. It can never do more than you can, and every call is logged in your name.') }}</flux:text>
    </div>

    @if ($oauth)
        <flux:tab.group>
            <flux:tabs variant="segmented" wire:model.live="tab">
                <flux:tab name="mcp" icon="link">MCP</flux:tab>
                <flux:tab name="tokens" icon="key">{{ __('API tokens') }}</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="mcp" class="pt-8">
                @include('mcp-kit::tokens.sign-in')
            </flux:tab.panel>

            <flux:tab.panel name="tokens" class="pt-8">
                @include('mcp-kit::tokens.tokens-tab')
            </flux:tab.panel>
        </flux:tab.group>
    @else
        @include('mcp-kit::tokens.tokens-tab')

        <section class="space-y-3">
            <flux:heading size="lg">{{ __('Things to ask') }}</flux:heading>
            @include('mcp-kit::tokens.examples')
        </section>
    @endif

    @if (config('mcp-kit.ui.enabled'))
        <flux:accordion>
            <flux:accordion.item>
                <flux:accordion.heading>{{ __('What the assistant remembers about you') }}</flux:accordion.heading>
                <flux:accordion.content>
                    <livewire:mcp-kit.assistant-memory :embedded="true" />
                </flux:accordion.content>
            </flux:accordion.item>
        </flux:accordion>
    @endif
</div>
