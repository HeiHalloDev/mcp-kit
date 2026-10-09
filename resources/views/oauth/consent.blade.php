<x-mcp-kit::oauth.frame :title="__('Connect :client', ['client' => $client->name])">
    <h1>{{ __(':client wants to use :app as you', ['client' => $client->name, 'app' => $server->label]) }}</h1>
    <p class="muted">{{ __('Signed in as :name. After you allow it, the assistant can do what you tick below, and only what your own account may do. It sends you back to :host.', ['name' => $user->name ?? $user->email ?? '', 'host' => $redirectHost]) }}</p>

    <form method="POST" action="{{ url(trim((string) config('mcp-kit.oauth.route_prefix', 'oauth'), '/').'/authorize') }}">
        @csrf
        @foreach ($params as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach

        @if ($abilities === [])
            <p>{{ __('Your account has nothing this assistant could be given access to here.') }}</p>
            <div class="actions">
                <button type="submit" name="decision" value="deny" class="primary">{{ __('Back') }}</button>
            </div>
        @else
            <ul>
                @foreach ($abilities as $ability)
                    <li>
                        <label>
                            <input type="checkbox" name="abilities[]" value="{{ $ability['name'] }}" @checked($ability['checked'])>
                            <span>
                                {{ $ability['description'] }}
                                @if ($ability['writes'])<span class="tag">{{ __('can change') }}</span>@endif
                                @if ($ability['explicit'] ?? false)<span class="tag">{{ __('high risk, off unless you tick it') }}</span>@endif
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>

            <p class="muted">{{ __('You can end this connection at any time under Connected apps.') }}</p>

            <div class="actions">
                <button type="submit" name="decision" value="deny">{{ __('Cancel') }}</button>
                <button type="submit" name="decision" value="allow" class="primary">{{ __('Allow') }}</button>
            </div>
        @endif
    </form>
</x-mcp-kit::oauth.frame>
