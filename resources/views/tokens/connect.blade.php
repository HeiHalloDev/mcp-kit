<div class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('Connect') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('The connection is self-describing — once connected, the assistant sees the tools and what they do. One line per server the token reaches. Pick your client:') }}</flux:text>
    </div>

    <flux:tab.group>
        <flux:tabs>
            <flux:tab name="claude-code">Claude Code</flux:tab>
            <flux:tab name="claude-desktop">Claude Desktop</flux:tab>
            <flux:tab name="codex">Codex</flux:tab>
            <flux:tab name="curl">cURL</flux:tab>
            <flux:tab name="prompts">{{ __('Examples') }}</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="claude-code">
            <flux:text size="sm" class="mb-2">{{ __('Run once in the terminal:') }}</flux:text>
            <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 text-xs text-zinc-100"><code>{{ $this->snippets()->claudeCode($this->servers, $tokenPlaceholder) }}</code></pre>
            <flux:text size="sm" class="mt-2">{{ __('--scope user makes the connection available from every folder; without it, it only works in the folder you ran the command from.') }}</flux:text>
        </flux:tab.panel>

        <flux:tab.panel name="claude-desktop">
            <flux:text size="sm" class="mb-2">{{ __('Add to the MCP configuration (Claude Desktop: Settings → Developer → Edit Config):') }}</flux:text>
            <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 text-xs text-zinc-100"><code>{{ $this->snippets()->claudeDesktop($this->servers, $tokenPlaceholder) }}</code></pre>
        </flux:tab.panel>

        <flux:tab.panel name="codex">
            <flux:text size="sm" class="mb-2">{{ __('Run once in the terminal:') }}</flux:text>
            <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 text-xs text-zinc-100"><code>{{ $this->snippets()->codex($this->servers, $tokenPlaceholder) }}</code></pre>
            <flux:text size="sm" class="mt-2">{{ __('Or in ~/.codex/config.toml:') }}</flux:text>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-zinc-900 p-4 text-xs text-zinc-100"><code>{{ $this->snippets()->codexToml($this->servers, $tokenPlaceholder) }}</code></pre>
        </flux:tab.panel>

        <flux:tab.panel name="curl">
            <flux:text size="sm" class="mb-2">{{ __('List the tools directly:') }}</flux:text>
            <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 text-xs text-zinc-100"><code>{{ $this->snippets()->curl($this->servers, $tokenPlaceholder) }}</code></pre>
        </flux:tab.panel>

        <flux:tab.panel name="prompts">
            @include('mcp-kit::tokens.examples')
        </flux:tab.panel>
    </flux:tab.group>

    <flux:field>
        <flux:label>{{ __('First message to the assistant') }}</flux:label>
        <flux:description>{{ __('Paste as the first message of a new session after setup — it connects, reads who you are and explains what it can help with.') }}</flux:description>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-zinc-900 p-4 text-xs text-zinc-100"><code>@include('mcp-kit::tokens.first-message')</code></pre>
    </flux:field>

    <flux:callout icon="exclamation-triangle" variant="warning">
        <flux:callout.text>
            {{ __('Never paste the token itself into a chat — it ends up in the conversation history. The token belongs only in the setup above. If it is exposed, revoke it here right away and create a new one.') }}
        </flux:callout.text>
    </flux:callout>
</div>
