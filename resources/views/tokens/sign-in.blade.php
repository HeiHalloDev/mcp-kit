{{--
    Sign in with a URL (mcp-kit.oauth). Shown above the token tabs when
    OAuth is on: no token to mint, copy or leak, so it is the way to connect
    and the tokens are the alternative. The name and the address are the
    whole setup, so each gets a row and a copy button of its own, before any
    client's steps: a name shown as a mere caption reads as a heading, not
    as the thing to type into the form.
--}}
@php($signInUrls = $this->snippets()->signInUrls())
@php($claudeCodeSignIn = $this->snippets()->claudeCodeSignInLines())
@php($codexSignIn = $this->snippets()->codexSignInLines())
@php($connectedApps = config('mcp-kit.ui.connected_apps_page.enabled') && \Illuminate\Support\Facades\Route::has((string) config('mcp-kit.ui.connected_apps_page.name')) ? route((string) config('mcp-kit.ui.connected_apps_page.name')) : null)

@if ($signInUrls !== [])
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Sign in with a URL') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('No token needed. Add the address to your assistant and sign in with your usual login when the browser opens. The assistant can do what you can do here and nothing more, and it follows your permissions when they change.') }}</flux:text>
        </div>

        <div class="space-y-3">
            @foreach ($signInUrls as $name => $url)
                @include('mcp-kit::tokens.fields', ['fields' => [__('Name') => $name, __('URL') => $url]])
            @endforeach
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
            </flux:tab.panel>
        </flux:tab.group>

        @if ($connectedApps)
            <flux:text size="sm" class="opacity-70">
                {{ __('See or end the sign-ins you have made:') }}
                <flux:link href="{{ $connectedApps }}">{{ __('Connected apps') }}</flux:link>
            </flux:text>
        @endif
    </div>

    <flux:separator />
@endif
