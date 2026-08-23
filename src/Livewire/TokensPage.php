<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Livewire;

use Flux\Flux;
use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\TokenPolicy;
use HeiHallo\McpKit\Exceptions\TokenRefused;
use HeiHallo\McpKit\Servers\ServerRegistry;
use HeiHallo\McpKit\Tokens\ConnectSnippets;
use HeiHallo\McpKit\Tokens\TokenMinter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Personal tokens: mint with a preset (filtered by the person's own
 * permissions), revoke, copy the connect snippets. Privileged users see
 * and may revoke everyone's tokens — offboarding is their job.
 */
class TokensPage extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    public string $preset = '';

    /** @var list<string> Explicit-only abilities added on top of the preset. */
    public array $extras = [];

    public ?int $expiresDays = null;

    public ?string $plainTextToken = null;

    /** @var list<string> */
    public array $mintedAbilities = [];

    public function mount(): void
    {
        $this->preset = array_key_first($this->presets) ?? '';
        $this->expiresDays = app(TokenPolicy::class)->defaultDays() ?? array_key_first($this->expiryOptions);
    }

    #[Computed]
    public function canSeeAll(): bool
    {
        return (bool) app(PrincipalResolver::class)->resolve(auth()->user())?->privileged;
    }

    /**
     * @return array<string, array{label: string, description: string, abilities: list<string>}>
     */
    #[Computed]
    public function presets(): array
    {
        return app(PresetResolver::class)->availableFor(auth()->user());
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function extraOptions(): array
    {
        return app(PresetResolver::class)->extrasFor(auth()->user());
    }

    /**
     * Lifetimes to pick from: the usual four, capped by the policy's maximum,
     * with the default always present and marked.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function expiryOptions(): array
    {
        $policy = app(TokenPolicy::class);
        $max = $policy->maxDays();
        $default = $policy->defaultDays();
        $days = array_filter([30, 90, 180, 365], fn (int $d): bool => $max === null || $d <= $max);

        if ($default !== null && $default > 0) {
            $days[] = $default;
        }

        $days = array_values(array_unique($days));
        sort($days);

        $options = [];

        foreach ($days as $d) {
            $options[$d] = $d === $default
                ? __(':days days (recommended)', ['days' => $d])
                : __(':days days', ['days' => $d]);
        }

        return $options;
    }

    #[Computed]
    public function tokens(): Collection
    {
        $policy = app(TokenPolicy::class);
        $model = Sanctum::$personalAccessTokenModel;
        $user = auth()->user();

        return $model::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->when(! $this->canSeeAll, fn ($query) => $query->where('tokenable_id', $user->getAuthIdentifier()))
            ->with('tokenable')
            ->latest('id')
            ->get()
            ->filter(fn (PersonalAccessToken $token): bool => $policy->isKitToken((string) $token->name))
            ->values();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function servers(): array
    {
        $abilities = $this->mintedAbilities !== []
            ? $this->mintedAbilities
            : [...($this->presets[$this->preset]['abilities'] ?? []), ...$this->extras];

        $servers = app(AbilityCatalogue::class)->serversFor($abilities);

        return $servers !== [] ? $servers : array_slice(app(ServerRegistry::class)->keys(), 0, 1);
    }

    public function create(): void
    {
        $this->validate();

        abort_unless(array_key_exists($this->preset, $this->presets), 403);
        abort_unless(array_key_exists((int) $this->expiresDays, $this->expiryOptions), 422);

        $abilities = $this->presets[$this->preset]['abilities'];

        // Explicit-only extras are enforced server-side, not just hidden in
        // the form: only a privileged owner can mint them.
        $extras = array_values(array_intersect($this->extras, array_keys($this->extraOptions)));
        $abilities = array_values(array_unique([...$abilities, ...$extras]));

        try {
            $token = app(TokenMinter::class)->mint(auth()->user(), trim($this->name), $abilities, $this->expiresDays, preset: $this->preset);
        } catch (TokenRefused $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->plainTextToken = $token->plainTextToken;
        $this->mintedAbilities = $abilities;
        $this->reset('name', 'extras');
        unset($this->tokens, $this->servers);

        $this->toast(__('Token created — copy it now, it is shown only once.'));
    }

    public function revoke(int $tokenId): void
    {
        $model = Sanctum::$personalAccessTokenModel;
        $user = auth()->user();

        /** @var PersonalAccessToken $token */
        $token = $model::query()->where('tokenable_type', $user->getMorphClass())->findOrFail($tokenId);

        abort_unless(app(TokenPolicy::class)->isKitToken((string) $token->name), 404);
        abort_unless((string) $token->tokenable_id === (string) $user->getAuthIdentifier() || $this->canSeeAll, 403);

        app(TokenMinter::class)->revoke($token, app(PrincipalResolver::class)->resolve($user));

        unset($this->tokens);

        $this->toast(__('Token revoked.'));
    }

    /**
     * @param  list<string>  $abilities
     */
    public function scopeColor(array $abilities): string
    {
        $presets = app(PresetResolver::class);

        return match (true) {
            $presets->grantsExplicitOnly($abilities) => 'red',
            $presets->grantsWrite($abilities) => 'amber',
            default => 'zinc',
        };
    }

    /**
     * @param  list<string>  $abilities
     */
    public function scopeLabel(array $abilities): string
    {
        return app(PresetResolver::class)->labelForAbilities($abilities);
    }

    public function tokenLabel(string $name): string
    {
        return app(TokenPolicy::class)->label($name);
    }

    public function snippets(): ConnectSnippets
    {
        return app(ConnectSnippets::class);
    }

    protected function toast(string $text): void
    {
        if (class_exists(Flux::class)) {
            Flux::toast($text);
        }
    }

    public function render(): View
    {
        $view = view('mcp-kit::livewire.tokens-page', [
            'tokenPlaceholder' => $this->plainTextToken ?? '<your-token>',
        ]);

        $layout = config('mcp-kit.ui.layout');

        return is_string($layout) && $layout !== '' ? $view->layout($layout) : $view;
    }
}
