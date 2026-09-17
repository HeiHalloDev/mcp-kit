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
            @php($codexApp = $this->snippets()->codexAppFields($this->servers, $tokenPlaceholder))
            @php($codexRemove = $this->snippets()->codexRemoveLines($this->servers))

            <div class="space-y-3">
                <flux:text size="sm">{{ __('In the ChatGPT app: Plugins → MCPs → add a server, then copy these values into the form — one server per form. Leave "Bearer token env var" empty: the token goes in as a header row under "Headers" (not "Headers from environment variables"):') }}</flux:text>
                @foreach ($codexApp as $name => $fields)
                    @include('mcp-kit::tokens.fields', ['fields' => $fields, 'label' => $name])
                @endforeach
            </div>

            <div class="space-y-3">
                <flux:text size="sm">{{ __('Or from a terminal — paste this once, press enter, and fully quit and reopen the ChatGPT app afterwards. The terminal, the ChatGPT app and the IDE extension all read the same file:') }}</flux:text>
                @include('mcp-kit::tokens.snippet', [
                    'code' => $this->snippets()->codexTerminal($this->servers, $tokenPlaceholder),
                    'copyLabel' => __('Copy'),
                ])
            </div>

            @if (config('mcp-kit.learning.enabled', false) || config('mcp-kit.gaps.enabled', true))
                <div class="space-y-3">
                    <flux:text size="sm">{{ __('Then tell Codex to record its work. Codex reads ~/.codex/AGENTS.md at the start of every thread, in the ChatGPT app too. Paste this once in a terminal. Running it again, from this app or another, replaces the block instead of adding a second, and leaves the rest of the file alone:') }}</flux:text>
                    @include('mcp-kit::tokens.snippet', [
                        'code' => $this->snippets()->codexInstructionsTerminal(),
                        'copyLabel' => __('Copy'),
                    ])
                    <flux:text size="sm" class="opacity-70">{{ __('Or add these lines to ~/.codex/AGENTS.md yourself:') }}</flux:text>
                    @include('mcp-kit::tokens.snippet', ['code' => $this->snippets()->codexInstructions(), 'wrap' => true])
                </div>
            @endif

            <div class="space-y-3">
                <flux:text size="sm">{{ __('Remove a connection') }}</flux:text>
                <flux:text size="sm" class="opacity-70">{{ __('In the ChatGPT app, uninstall the server under Plugins → MCPs — or from a terminal:') }}</flux:text>
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
        @include('mcp-kit::tokens.snippet', ['code' => trim(view('mcp-kit::tokens.first-message')->render()), 'wrap' => true])
    </flux:field>

    <flux:callout icon="exclamation-triangle" variant="warning">
        <flux:callout.text>
            {{ __('Never paste the token itself into a chat — it ends up in the conversation history. The token belongs only in the setup above. If it is exposed, revoke it here right away and create a new one.') }}
        </flux:callout.text>
    </flux:callout>
</div>
