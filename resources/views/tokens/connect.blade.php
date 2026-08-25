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

        <flux:tab.panel name="claude-code" class="space-y-4">
            @php($claudeLines = $this->snippets()->claudeCodeLines($this->servers, $tokenPlaceholder))

            @include('mcp-kit::tokens.snippet', [
                'code' => implode("\n", $claudeLines),
                'label' => count($claudeLines) > 1 ? __('Run once in the terminal — all servers at once:') : __('Run once in the terminal:'),
                'copyLabel' => count($claudeLines) > 1 ? __('Copy all') : __('Copy'),
            ])

            @if (count($claudeLines) > 1)
                <flux:accordion>
                    <flux:accordion.item>
                        <flux:accordion.heading>{{ __('One server at a time') }}</flux:accordion.heading>
                        <flux:accordion.content class="space-y-3">
                            @foreach ($claudeLines as $name => $line)
                                @include('mcp-kit::tokens.snippet', ['code' => $line, 'label' => $name])
                            @endforeach
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>
            @endif

            <flux:accordion>
                <flux:accordion.item>
                    <flux:accordion.heading>{{ __('Remove a connection') }}</flux:accordion.heading>
                    <flux:accordion.content class="space-y-3">
                        <flux:text size="sm">{{ __('Removing a connection only forgets it on this machine — it does not revoke the token. Revoke it in the list above.') }}</flux:text>
                        @include('mcp-kit::tokens.snippet', [
                            'code' => $this->snippets()->claudeCodeRemove($this->servers),
                            'copyLabel' => count($claudeLines) > 1 ? __('Copy all') : __('Copy'),
                        ])
                    </flux:accordion.content>
                </flux:accordion.item>
            </flux:accordion>

            <flux:text size="sm">{{ __('--scope user makes the connection available from every folder; without it, it only works in the folder you ran the command from.') }}</flux:text>
        </flux:tab.panel>

        <flux:tab.panel name="claude-desktop">
            @include('mcp-kit::tokens.snippet', [
                'code' => $this->snippets()->claudeDesktop($this->servers, $tokenPlaceholder),
                'label' => __('Add to the MCP configuration (Claude Desktop: Settings → Developer → Edit Config):'),
            ])
        </flux:tab.panel>

        <flux:tab.panel name="codex" class="space-y-4">
            @php($codexLines = $this->snippets()->codexLines($this->servers, $tokenPlaceholder))

            @include('mcp-kit::tokens.snippet', [
                'code' => implode("\n", $codexLines),
                'label' => count($codexLines) > 1 ? __('Run once in the terminal — all servers at once:') : __('Run once in the terminal:'),
                'copyLabel' => count($codexLines) > 1 ? __('Copy all') : __('Copy'),
            ])

            @if (count($codexLines) > 1)
                <flux:accordion>
                    <flux:accordion.item>
                        <flux:accordion.heading>{{ __('One server at a time') }}</flux:accordion.heading>
                        <flux:accordion.content class="space-y-3">
                            @foreach ($codexLines as $name => $line)
                                @include('mcp-kit::tokens.snippet', ['code' => $line, 'label' => $name])
                            @endforeach
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>
            @endif

            @include('mcp-kit::tokens.snippet', [
                'code' => $this->snippets()->codexToml($this->servers, $tokenPlaceholder),
                'label' => __('Or in ~/.codex/config.toml:'),
            ])

            <flux:accordion>
                <flux:accordion.item>
                    <flux:accordion.heading>{{ __('Remove a connection') }}</flux:accordion.heading>
                    <flux:accordion.content>
                        @include('mcp-kit::tokens.snippet', [
                            'code' => $this->snippets()->codexRemove($this->servers),
                            'copyLabel' => count($codexLines) > 1 ? __('Copy all') : __('Copy'),
                        ])
                    </flux:accordion.content>
                </flux:accordion.item>
            </flux:accordion>
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
