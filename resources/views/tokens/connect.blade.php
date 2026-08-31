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

        <flux:tab.panel name="claude-code" class="space-y-5">
            @php($claudeLines = $this->snippets()->claudeCodeLines($this->servers, $tokenPlaceholder))
            @php($claudeRemove = $this->snippets()->claudeCodeRemoveLines($this->servers))

            @if (count($claudeLines) > 1)
                @include('mcp-kit::tokens.snippet', [
                    'code' => implode("\n", $claudeLines),
                    'label' => __('Run once in the terminal — all servers at once:'),
                    'copyLabel' => __('Copy all'),
                ])

                <div class="space-y-3">
                    <flux:text size="sm">{{ __('One server at a time') }}</flux:text>
                    @foreach ($claudeLines as $name => $line)
                        @include('mcp-kit::tokens.snippet', ['code' => $line, 'label' => $name])
                    @endforeach
                </div>
            @else
                @include('mcp-kit::tokens.snippet', [
                    'code' => implode("\n", $claudeLines),
                    'label' => __('Run once in the terminal:'),
                ])
            @endif

            <flux:text size="sm">{{ __('--scope user makes the connection available from every folder; without it, it only works in the folder you ran the command from.') }}</flux:text>

            <div class="space-y-3">
                <flux:text size="sm">{{ __('Remove a connection') }}</flux:text>
                <flux:text size="sm" class="opacity-70">{{ __('Removing a connection only forgets it on this machine — it does not revoke the token. Revoke it in the list above.') }}</flux:text>

                @if (count($claudeRemove) > 1)
                    @include('mcp-kit::tokens.snippet', [
                        'code' => implode("\n", $claudeRemove),
                        'copyLabel' => __('Copy all'),
                    ])
                    @foreach ($claudeRemove as $name => $line)
                        @include('mcp-kit::tokens.snippet', ['code' => $line, 'label' => $name])
                    @endforeach
                @else
                    @include('mcp-kit::tokens.snippet', ['code' => implode("\n", $claudeRemove)])
                @endif
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="claude-desktop">
            @include('mcp-kit::tokens.snippet', [
                'code' => $this->snippets()->claudeDesktop($this->servers, $tokenPlaceholder),
                'label' => __('Add to the MCP configuration (Claude Desktop: Settings → Developer → Edit Config):'),
            ])
        </flux:tab.panel>

        <flux:tab.panel name="codex" class="space-y-5">
            @php($codexLines = $this->snippets()->codexLines($this->servers, $tokenPlaceholder))
            @php($codexRemove = $this->snippets()->codexRemoveLines($this->servers))

            @include('mcp-kit::tokens.snippet', [
                'code' => $this->snippets()->codexToml($this->servers, $tokenPlaceholder),
                'label' => __('Append to ~/.codex/config.toml:'),
                'copyLabel' => count($codexLines) > 1 ? __('Copy all') : __('Copy'),
            ])

            <flux:text size="sm">{{ __('The token lives in the file, not in the environment — the terminal, the ChatGPT app and the IDE extension all read it, and no shell exports are needed.') }}</flux:text>

            @if (count($codexLines) > 1)
                <div class="space-y-3">
                    <flux:text size="sm">{{ __('One server at a time') }}</flux:text>
                    @foreach ($codexLines as $name => $block)
                        @include('mcp-kit::tokens.snippet', ['code' => $block, 'label' => $name])
                    @endforeach
                </div>
            @endif

            <div class="space-y-3">
                <flux:text size="sm">{{ __('Remove a connection') }}</flux:text>
                @include('mcp-kit::tokens.snippet', [
                    'code' => implode("\n", $codexRemove),
                    'copyLabel' => count($codexRemove) > 1 ? __('Copy all') : __('Copy'),
                ])
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="curl">
            @include('mcp-kit::tokens.snippet', [
                'code' => $this->snippets()->curl($this->servers, $tokenPlaceholder),
                'label' => __('List the tools directly:'),
            ])
        </flux:tab.panel>

        <flux:tab.panel name="prompts">
            @include('mcp-kit::tokens.examples')
        </flux:tab.panel>
    </flux:tab.group>

    <flux:field>
        <flux:label>{{ __('First message to the assistant') }}</flux:label>
        <flux:description>{{ __('Paste as the first message of a new session after setup — it connects, reads who you are and explains what it can help with.') }}</flux:description>
        @include('mcp-kit::tokens.snippet', ['code' => trim(view('mcp-kit::tokens.first-message')->render())])
    </flux:field>

    <flux:callout icon="exclamation-triangle" variant="warning">
        <flux:callout.text>
            {{ __('Never paste the token itself into a chat — it ends up in the conversation history. The token belongs only in the setup above. If it is exposed, revoke it here right away and create a new one.') }}
        </flux:callout.text>
    </flux:callout>
</div>
