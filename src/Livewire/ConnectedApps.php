<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Livewire;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Models\OAuthGrant;
use HeiHallo\McpKit\OAuth\AuthorizationServer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The AI clients a person has let in through a sign-in, and a way to end
 * each one. Only ever the person's own: a grant is theirs to give and
 * theirs to take back.
 */
class ConnectedApps extends Component
{
    /**
     * @return Collection<int, OAuthGrant>
     */
    #[Computed]
    public function grants(): Collection
    {
        return OAuthGrant::query()
            ->active()
            ->with('client')
            ->where('user_id', (string) auth()->id())
            ->latest()
            ->get();
    }

    public function describe(string $ability): string
    {
        return app(AbilityCatalogue::class)->description($ability) ?? $ability;
    }

    public function disconnect(int $grant): void
    {
        $row = OAuthGrant::query()->active()->where('user_id', (string) auth()->id())->findOrFail($grant);
        $user = auth()->user();

        app(AuthorizationServer::class)->revoke($row, 'person_revoked', $user !== null ? app(PrincipalResolver::class)->resolve($user) : null);

        unset($this->grants);
    }

    public function render(): View
    {
        $view = view('mcp-kit::livewire.connected-apps');
        $layout = config('mcp-kit.ui.layout');

        return is_string($layout) && $layout !== '' ? $view->layout($layout) : $view;
    }
}
