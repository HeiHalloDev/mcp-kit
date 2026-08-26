<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests;

use HeiHallo\McpKit\McpKitServiceProvider;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeReportsServer;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use HeiHallo\McpKit\Tests\Fixtures\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;
use Spatie\Activitylog\ActivitylogServiceProvider;

use function Orchestra\Testbench\load_migration_paths;

abstract class TestCase extends Orchestra
{
    public const PERMISSIONS = ['staff', 'things', 'reports', 'admin'];

    protected function setUp(): void
    {
        parent::setUp();

        // Flux is not installable in CI; the harness provides stand-ins for
        // the components the package views use.
        Blade::anonymousComponentPath(__DIR__.'/resources/flux', 'flux');

        foreach (self::PERMISSIONS as $permission) {
            Gate::define($permission, fn ($user): bool => in_array($permission, (array) ($user->permissions ?? []), true));
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        load_migration_paths($this->app, __DIR__.'/database/migrations');
        load_migration_paths($this->app, __DIR__.'/../vendor/laravel/sanctum/database/migrations');
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            McpServiceProvider::class,
            ActivitylogServiceProvider::class,
            LivewireServiceProvider::class,
            McpKitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $database = (string) env('DB_DATABASE', 'mcp_kit_testbench');

        if (! str_starts_with($database, 'mcp_kit_testbench')) {
            throw new RuntimeException(
                "Package tests refuse to run against database '{$database}' — the suite wipes it. Use a database named mcp_kit_testbench*.",
            );
        }

        $this->configureDatabase($app, $database);

        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $config->set('app.name', 'Acme');
        $config->set('auth.providers.users.model', User::class);
        $config->set('cache.default', 'array');
        $config->set('activitylog.table_name', 'activity_log');

        $config->set('mcp-kit.scheme', 'acme');
        $config->set('mcp-kit.permission_rules.staff_permission', 'staff');
        $config->set('mcp-kit.permission_rules.privileged_roles', ['admin']);
        $config->set('mcp-kit.permission_rules.privileged_label', 'an Admin role');
        $config->set('mcp-kit.permission_rules.known', self::PERMISSIONS);
        $config->set('mcp-kit.servers', [
            'acme' => [
                'class' => AcmeServer::class,
                'path' => '/mcp/acme',
                'label' => 'Acme',
                'wildcard' => 'acme:*',
                'client_name' => 'acme',
                'requires_staff' => true,
                'description' => 'Things and events.',
            ],
            'reports' => [
                'class' => AcmeReportsServer::class,
                'path' => '/mcp/reports',
                'label' => 'Reports',
                'wildcard' => 'reports:*',
                'client_name' => 'acme-reports',
                'requires_staff' => false,
                'service_clients' => false,
            ],
            // The path a merged-away server used to answer on. Serves the
            // target's class; access is decided as if the caller hit /mcp/acme.
            'legacy' => [
                'class' => AcmeServer::class,
                'path' => '/mcp/legacy',
                'alias_of' => 'acme',
                'label' => 'Legacy (alias)',
                'client_name' => 'acme-legacy',
                'presets' => false,
            ],
        ]);
        $config->set('mcp-kit.catalogue', [
            'abilities' => [
                'acme:things:read' => ['Search and view things', 'things', 'acme'],
                'acme:things:write' => ['Create and change things', 'things', 'acme'],
                'acme:events:write' => ['Log events on a thing', null, 'acme'],
                'acme:admin' => ['Change what other people may do', 'admin', 'acme'],
                'reports:read' => ['Aggregate numbers, never a person', 'reports', 'reports'],
            ],
            'explicit_only' => ['acme:admin'],
            'service_client_writes' => ['acme:events:write'],
            'aliases' => ['old:things:read' => 'acme:things:read'],
            'super_wildcard' => null,
            'read_abilities' => [],
        ]);
        $config->set('mcp-kit.token_presets.analyst', [
            'label' => 'Analyst',
            'description' => 'The numbers only.',
            'grant' => ['reports:read'],
        ]);
        $config->set('mcp-kit.onboarding.customer_facing_abilities', ['acme:things:write']);
        $config->set('mcp-kit.suggestions', [
            'acme:things:read' => ['Ask for the things changed this week.', 'Look up one thing by name.'],
            'acme:things:write' => ['Rename a thing, preview first.'],
            'reports:read' => ['Ask for the monthly numbers.'],
        ]);
        // A fresh clone has no tests/tmp — git does not carry empty directories,
        // so CI failed here while every machine that had run the suite once
        // passed.
        if (! is_dir(__DIR__.'/tmp')) {
            mkdir(__DIR__.'/tmp', 0755, true);
        }

        $config->set('mcp-kit.docs.path', __DIR__.'/tmp/tools.md');
        $config->set('mcp-kit.docs.inventory', __DIR__.'/tmp/tool-inventory.json');
        $config->set('mcp-kit.ui.check_dependencies', false);
    }

    private function configureDatabase(Application $app, string $database): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => $database,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
    }
}
