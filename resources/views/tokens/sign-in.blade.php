{{--
    The MCP tab of the Connect page (mcp-kit.oauth): sign in with a URL.
    With OAuth on this is the whole first view, so it holds only what a
    person needs to connect: three numbered steps, things to ask, and the
    sign-ins they already made. Tokens live on the other tab.

    The name and the address each get a row and a copy button of their own:
    a name shown as a caption reads as a heading, not as the thing to type
    into the connector form.
--}}
@php($signInUrls = $this->snippets()->signInUrls())
@php($claudeCodeSignIn = $this->snippets()->claudeCodeSignInLines())
@php($codexSignIn = $this->snippets()->codexSignInLines())
@php($codexRecords = config('mcp-kit.learning.enabled', false) || config('mcp-kit.gaps.enabled', true))

<div class="space-y-10">
    <flux:text>{{ __('No token needed. Add the address to your assistant and sign in with your usual login when the browser opens. The assistant can do what you can do here and nothing more, and it follows your permissions when they change.') }}</flux:text>

    <section class="space-y-4">
        <div class="flex items-center gap-3">
            <flux:badge size="sm">1</flux:badge>
            <flux:heading size="lg">{{ __('Copy the name and the address') }}</flux:heading>
        </div>

        <div class="space-y-3">
            @foreach ($signInUrls as $name => $url)
                @include('mcp-kit::tokens.fields', ['fields' => [__('Name') => $name, __('URL') => $url]])
            @endforeach
        </div>
    </section>

    <section class="space-y-4">
        <div class="flex items-center gap-3">
            <flux:badge size="sm">2</flux:badge>
            <flux:heading size="lg">{{ __('Add it to your assistant') }}</flux:heading>
        </div>

        <flux:tab.group>
            <flux:tabs>
                <flux:tab name="sign-in-claude">Claude</flux:tab>
                <flux:tab name="sign-in-claude-code">Claude Code</flux:tab>
                <flux:tab name="sign-in-codex">Codex</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="sign-in-claude" class="space-y-3">
                <ol class="list-decimal space-y-1.5 pl-5 text-sm text-zinc-600 dark:text-zinc-300">
                    <li>{{ __('In Claude (the app or claude.ai), open Settings → Connectors.') }}</li>
                    <li>{{ __('Choose Add custom connector.') }}</li>
                    <li>{{ __('Copy the Name and the URL above into the two fields of the same name. Leave the advanced settings empty.') }}</li>
                    <li>{{ __('Click Add, then Connect. Sign in when the browser asks, and approve on the page that follows.') }}</li>
                </ol>
                <flux:text size="sm" class="opacity-70">{{ __('A connector added on claude.ai is there in the desktop and mobile apps too.') }}</flux:text>
            </flux:tab.panel>

            <flux:tab.panel name="sign-in-claude-code" class="space-y-3">
                @include('mcp-kit::tokens.snippet', [
                    'code' => implode("\n", $claudeCodeSignIn),
                    'label' => __('Run once in the terminal:'),
                    'copyLabel' => count($claudeCodeSignIn) > 1 ? __('Copy all') : __('Copy'),
                ])
                <flux:text size="sm">{{ __('Then start Claude Code, type /mcp, pick the server and choose Authenticate. The browser opens for the sign-in.') }}</flux:text>
            </flux:tab.panel>

            <flux:tab.panel name="sign-in-codex" class="space-y-3">
                <ol class="list-decimal space-y-1.5 pl-5 text-sm text-zinc-600 dark:text-zinc-300">
                    <li>{{ __('In the Codex app, open Settings → Plugins → MCPs and choose Add MCP server.') }}</li>
                    <li>{{ __('Set Type to Streamable HTTP, then copy the Name and the URL above into the two fields of the same name. Leave the token and header fields empty.') }}</li>
                    <li>{{ __('Save. Sign in when the browser asks, then quit and reopen the app.') }}</li>
                </ol>
                @include('mcp-kit::tokens.snippet', [
                    'code' => implode("\n", $codexSignIn),
                    'label' => __('Or from a terminal. Codex opens the browser for the sign-in by itself:'),
                    'copyLabel' => count($codexSignIn) > 1 ? __('Copy all') : __('Copy'),
                ])

                @if ($codexRecords)
                    @include('mcp-kit::tokens.snippet', [
                        'code' => $this->snippets()->codexInstructionsTerminal(),
                        'label' => __('Then tell Codex to record its work. Codex reads ~/.codex/AGENTS.md at the start of every thread. Paste this once in a terminal; running it again replaces the block instead of adding a second:'),
                        'copyLabel' => __('Copy'),
                    ])
                @endif
            </flux:tab.panel>
        </flux:tab.group>
    </section>

    <section class="space-y-4">
        <div class="flex items-center gap-3">
            <flux:badge size="sm">3</flux:badge>
            <flux:heading size="lg">{{ __('Send the first message') }}</flux:heading>
        </div>

        <flux:text class="text-sm">{{ __('Open a new chat and send this before anything else. The assistant connects, reads who you are and the house rules, and tells you what it can help with.') }}</flux:text>
        @include('mcp-kit::tokens.snippet', ['code' => trim(view('mcp-kit::tokens.first-message')->render()), 'wrap' => true])
    </section>

    <section class="space-y-3">
        <flux:heading size="lg">{{ __('Things to ask') }}</flux:heading>
        @include('mcp-kit::tokens.examples')
    </section>

    <livewire:mcp-kit.connected-apps :embedded="true" />
</div>
