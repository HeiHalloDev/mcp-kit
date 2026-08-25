<?php

declare(strict_types=1);

namespace HeiHallo\McpKit;

use Flux\Flux;
use HeiHallo\McpKit\Activity\ActivityStamper;
use HeiHallo\McpKit\Activity\PruneMcpActivityCommand;
use HeiHallo\McpKit\Audit\McpCallContext;
use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Contracts\DocsRenderer;
use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Contracts\Links;
use HeiHallo\McpKit\Contracts\MemoryPolicy;
use HeiHallo\McpKit\Contracts\MemoryStore;
use HeiHallo\McpKit\Contracts\OnboardingQuestions;
use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Contracts\PlaybookPolicy;
use HeiHallo\McpKit\Contracts\PlaybookStore;
use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\ResolvesActivityChannel;
use HeiHallo\McpKit\Contracts\ResolvesActivitySource;
use HeiHallo\McpKit\Contracts\SuggestsTasks;
use HeiHallo\McpKit\Contracts\TaskStore;
use HeiHallo\McpKit\Contracts\TokenPolicy;
use HeiHallo\McpKit\Contracts\UserDescriber;
use HeiHallo\McpKit\Exceptions\UiDependenciesMissing;
use HeiHallo\McpKit\Exceptions\UnguardedMcpServer;
use HeiHallo\McpKit\Http\Middleware\EnsureMcpAccess;
use HeiHallo\McpKit\Learning\CurrentTask;
use HeiHallo\McpKit\Playbooks\PlaybookLimits;
use HeiHallo\McpKit\Playbooks\PlaybookPrompts;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;

class McpKitServiceProvider extends ServiceProvider
{
    /**
     * contract => config key holding the implementation class.
     *
     * @var array<class-string, string>
     */
    public const BINDINGS = [
        PrincipalResolver::class => 'mcp-kit.principal',
        PermissionChecker::class => 'mcp-kit.permissions',
        AbilityCatalogue::class => 'mcp-kit.abilities',
        PresetResolver::class => 'mcp-kit.presets',
        TokenPolicy::class => 'mcp-kit.token_policy',
        Links::class => 'mcp-kit.links',
        UserDescriber::class => 'mcp-kit.describer',
        AuditWriter::class => 'mcp-kit.audit',
        DocsRenderer::class => 'mcp-kit.docs.renderer',
        GroundRules::class => 'mcp-kit.ground_rules.class',
        MemoryStore::class => 'mcp-kit.memory.store',
        MemoryPolicy::class => 'mcp-kit.memory.policy',
        GapStore::class => 'mcp-kit.gaps.store',
        PlaybookStore::class => 'mcp-kit.playbooks.store',
        PlaybookPolicy::class => 'mcp-kit.playbooks.policy',
        TaskStore::class => 'mcp-kit.learning.store',
        OnboardingQuestions::class => 'mcp-kit.onboarding.questions',
        SuggestsTasks::class => 'mcp-kit.suggestions_class',
        ResolvesActivityChannel::class => 'mcp-kit.activity.channel_resolver',
        ResolvesActivitySource::class => 'mcp-kit.activity.source_resolver',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mcp-kit.php', 'mcp-kit');
        $this->backfillRegistryDefaults();

        $this->app->singleton(ServerRegistry::class);
        $this->app->scoped(McpCallContext::class);
        $this->app->singleton(ActivityStamper::class);
        $this->app->singleton(PlaybookPrompts::class);
        $this->app->scoped(CurrentTask::class);
        $this->app->bind(PlaybookLimits::class, fn (): PlaybookLimits => PlaybookLimits::fromConfig());

        // Every contract resolves from its config key, as a singleton. An
        // app that prefers code over config re-binds the contract in its
        // own provider; the later binding wins.
        foreach (self::BINDINGS as $contract => $key) {
            $this->app->singleton($contract, function ($app) use ($contract, $key) {
                $class = $app['config']->get($key);

                if (! is_string($class) || ! class_exists($class)) {
                    throw new \RuntimeException("mcp-kit: {$key} must name a class implementing {$contract}.");
                }

                return $app->make($class);
            });
        }

        $this->registerLogChannel();
    }

    /**
     * Laravel's config merge is shallow: an app that published the config
     * before a key existed would lose it. The maps that matter are filled
     * in underneath the app's entries; the app's values always win.
     */
    protected function backfillRegistryDefaults(): void
    {
        $defaults = require __DIR__.'/../config/mcp-kit.php';
        $config = $this->app['config'];

        foreach (['catalogue', 'tokens', 'routes', 'permission_rules', 'memory', 'activity', 'onboarding', 'docs', 'shared', 'ground_rules', 'me', 'instructions', 'describer_options', 'playbooks', 'gaps', 'learning'] as $section) {
            $config->set("mcp-kit.{$section}", array_merge($defaults[$section], (array) $config->get("mcp-kit.{$section}", [])));
        }

        $config->set('mcp-kit.memory.limits', array_merge($defaults['memory']['limits'], (array) $config->get('mcp-kit.memory.limits', [])));
        $config->set('mcp-kit.playbooks.limits', array_merge($defaults['playbooks']['limits'], (array) $config->get('mcp-kit.playbooks.limits', [])));
        $config->set('mcp-kit.ui', array_merge($defaults['ui'], (array) $config->get('mcp-kit.ui', [])));
        $config->set('mcp-kit.ui.tokens_page', array_merge($defaults['ui']['tokens_page'], (array) $config->get('mcp-kit.ui.tokens_page', [])));
    }

    /**
     * The `mcp` log channel, when the app has not defined one: a daily file
     * next to the default log.
     */
    protected function registerLogChannel(): void
    {
        $config = $this->app['config'];

        if ($config->has('logging.channels.mcp')) {
            return;
        }

        $config->set('logging.channels.mcp', [
            'driver' => 'daily',
            'path' => $this->app->storagePath('logs/mcp.log'),
            'level' => 'info',
            'days' => 30,
            'replace_placeholders' => true,
        ]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mcp-kit');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerRateLimiter();
        $this->registerServers();
        $this->registerActivityModel();
        $this->registerStamper();
        $this->registerUi();

        $this->app->booted(fn () => $this->enforceGuardedServers());

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\TokenCommand::class,
                Console\Commands\ClientTokenCommand::class,
                Console\Commands\DocsCommand::class,
                Console\Commands\AuditTokensCommand::class,
                Console\Commands\InstallCommand::class,
                PruneMcpActivityCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/mcp-kit.php' => config_path('mcp-kit.php'),
            ], 'mcp-kit-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/mcp-kit'),
            ], 'mcp-kit-views');
        }
    }

    /**
     * Keyed on the token, not the user or the IP: two harnesses running as
     * the same person should not throttle each other, and a shared egress
     * address should not throttle a whole office.
     */
    protected function registerRateLimiter(): void
    {
        RateLimiter::for('mcp-kit', function (Request $request): Limit {
            $perMinute = (int) (config('mcp-kit.routes.throttle') ?? 120);
            $user = $request->user();
            $token = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
            $key = $token?->id ?? $user?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(max(1, $perMinute))->by('mcp-kit:'.$key);
        });
    }

    protected function registerServers(): void
    {
        Mcp::macro('staff', fn (string $key): Route => McpKit::server($key));

        if (! $this->app->runningInConsole() && $this->app->routesAreCached()) {
            return;
        }

        foreach (app(ServerRegistry::class)->all() as $key => $definition) {
            McpKit::server($key, $definition);
        }
    }

    /**
     * The optional base model is used only when the app left the plain
     * Spatie model in place.
     */
    protected function registerActivityModel(): void
    {
        $config = $this->app['config'];

        if ($config->get('activitylog.activity_model') === \Spatie\Activitylog\Models\Activity::class) {
            $config->set('activitylog.activity_model', Activity\Activity::class);
        }
    }

    protected function registerStamper(): void
    {
        $model = (string) $this->app['config']->get('activitylog.activity_model', \Spatie\Activitylog\Models\Activity::class);

        Event::listen("eloquent.creating: {$model}", fn ($activity) => $this->app->make(ActivityStamper::class)($activity));
    }

    protected function registerUi(): void
    {
        $config = $this->app['config'];

        if (! $config->get('mcp-kit.ui.enabled')) {
            return;
        }

        if ($config->get('mcp-kit.ui.check_dependencies', true) && (! class_exists(\Livewire\Livewire::class) || ! class_exists(Flux::class))) {
            throw UiDependenciesMissing::create();
        }

        \Livewire\Livewire::component('mcp-kit.tokens-page', $this->livewireComponent('McpTokensPage', Livewire\TokensPage::class));
        \Livewire\Livewire::component('mcp-kit.assistant-memory', $this->livewireComponent('AssistantMemory', Livewire\AssistantMemory::class));

        if ($config->get('mcp-kit.ui.tokens_page.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/ui.php');
        }
    }

    /**
     * An app class of the same name under {AppNamespace}\Livewire wins over the
     * package component.
     *
     * @param  class-string  $default
     * @return class-string
     */
    protected function livewireComponent(string $name, string $default): string
    {
        $candidate = rtrim($this->app->getNamespace(), '\\').'\\Livewire\\'.$name;

        return class_exists($candidate) && is_subclass_of($candidate, $default) ? $candidate : $default;
    }

    /**
     * Every Mcp::web route must carry the kit's guards and must never carry
     * session middleware — otherwise a logged-in browser reaches the tools
     * with a TransientToken that answers yes to every ability.
     */
    protected function enforceGuardedServers(): void
    {
        if (! $this->app['config']->get('mcp-kit.routes.enforce', true)) {
            return;
        }

        $allowed = array_map(static fn ($uri): string => ltrim((string) $uri, '/'), (array) $this->app['config']->get('mcp-kit.routes.allow_unguarded', []));

        foreach ($this->app->make(Registrar::class)->servers() as $route) {
            if (! $route instanceof Route) {
                continue; // Mcp::local servers run on stdio, not HTTP.
            }

            if (in_array(ltrim($route->uri(), '/'), $allowed, true)) {
                continue;
            }

            $declared = array_map('strval', $route->middleware());
            $gathered = array_map('strval', $route->gatherMiddleware());

            if (in_array('web', $declared, true) || in_array(StartSession::class, $gathered, true)) {
                throw UnguardedMcpServer::forRoute($route->uri(), 'carries session middleware');
            }

            if (! in_array(EnsureMcpAccess::class, $gathered, true)) {
                throw UnguardedMcpServer::forRoute($route->uri(), 'is not guarded by EnsureMcpAccess');
            }
        }
    }
}
