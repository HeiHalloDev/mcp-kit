<?php

declare(strict_types=1);

use HeiHallo\McpKit\Docs\ToolReference;
use Illuminate\Support\Facades\File;

/**
 * The installer writes into the Testbench skeleton: a scratch app root.
 */
function installPaths(): array
{
    return [
        config_path('mcp-kit.php'),
        app_path('Mcp/Servers/AcmeServer.php'),
        resource_path('views/vendor/mcp-kit/ground-rules/intro.blade.php'),
        base_path('tests/Feature/Mcp/KitGuardsTest.php'),
        base_path('tests/Pest.php'),
        config('mcp-kit.docs.path'),
        config('mcp-kit.docs.inventory'),
        app_path('Mcp/Tools/ScannedTool.php'),
    ];
}

beforeEach(function () {
    foreach (installPaths() as $path) {
        @unlink($path);
    }
});

afterEach(function () {
    foreach (installPaths() as $path) {
        @unlink($path);
    }

    File::deleteDirectory(app_path('Mcp'));
    File::deleteDirectory(resource_path('views/vendor/mcp-kit'));
});

test('mcp:install scaffolds config, server, ground-rules intro, guard test, docs and inventory, and is idempotent', function () {
    File::ensureDirectoryExists(base_path('tests'));
    file_put_contents(base_path('tests/Pest.php'), "<?php\n\nuses(Tests\\TestCase::class)->in('Feature');\n");

    $this->artisan('mcp:install')
        ->expectsOutputToContain('config/mcp-kit.php')
        ->expectsOutputToContain('app/Mcp/Servers/AcmeServer.php')
        ->expectsOutputToContain('tests/Feature/Mcp/KitGuardsTest.php')
        ->expectsOutputToContain('Guards::actors(...) appended')
        ->assertSuccessful();

    expect(file_exists(config_path('mcp-kit.php')))->toBeTrue()
        ->and((string) file_get_contents(app_path('Mcp/Servers/AcmeServer.php')))->toContain('class AcmeServer extends StaffServer')
        ->and((string) file_get_contents(app_path('Mcp/Servers/AcmeServer.php')))->toContain('namespace App\\Mcp\\Servers;')
        ->and((string) file_get_contents(app_path('Mcp/Servers/AcmeServer.php')))->toContain('acme://ground-rules')
        ->and((string) file_get_contents(resource_path('views/vendor/mcp-kit/ground-rules/intro.blade.php')))->toContain('What this app is: Acme.')
        ->and((string) file_get_contents(base_path('tests/Feature/Mcp/KitGuardsTest.php')))->toContain('Guards::all(inventory:')
        ->and((string) file_get_contents(base_path('tests/Pest.php')))->toContain('Guards::actors(')
        ->and((string) file_get_contents(config('mcp-kit.docs.path')))->toContain('<!-- generated:tools:start -->')
        ->and(json_decode((string) file_get_contents(config('mcp-kit.docs.inventory')), true))->toBe(['acme' => ['admin_only', 'attach_file', 'list_things', 'log_event', 'update_thing'], 'reports' => ['monthly_numbers']]);

    // Second run: everything exists, nothing is appended twice.
    $this->artisan('mcp:install')
        ->expectsOutputToContain('exists')
        ->expectsOutputToContain('actors registered')
        ->assertSuccessful();

    expect(substr_count((string) file_get_contents(base_path('tests/Pest.php')), 'Guards::actors('))->toBe(1);
});

test('mcp:install --scan adds the abilities the tools check to the published config', function () {
    File::ensureDirectoryExists(app_path('Mcp/Tools'));
    file_put_contents(app_path('Mcp/Tools/ScannedTool.php'), "<?php\nnamespace App\\Mcp\\Tools;\nclass ScannedTool { public function handle() { return \$this->requireAbility(\$r, 'acme:scanned:read') ?? \$this->requireAbility(\$r, 'acme:things:read'); } }\n");

    $this->artisan('mcp:install', ['--scan' => true])
        ->expectsOutputToContain('1 abilities added')
        ->assertSuccessful();

    expect((string) file_get_contents(config_path('mcp-kit.php')))
        ->toContain("'acme:scanned:read' => ['TODO describe', null, 'acme'],")
        ->not->toContain("'acme:things:read' => ['TODO describe'");
});

test('mcp:install --with-tokens-page switches the UI on in the published config', function () {
    $this->artisan('mcp:install', ['--with-tokens-page' => true])->assertSuccessful();

    expect((string) file_get_contents(config_path('mcp-kit.php')))
        ->toContain("env('MCP_KIT_TOKENS_PAGE', true)")
        ->toContain("env('MCP_KIT_UI', true)");
});

test('an existing file is never overwritten, --force or not', function () {
    $this->artisan('mcp:install')->assertSuccessful();

    File::put(config_path('mcp-kit.php'), "<?php return ['edited' => true];");
    File::put((string) config('mcp-kit.docs.path'), "# 550 lines of hand-written docs\n<!-- generated:tools:start -->\n<!-- generated:tools:end -->\n");

    $this->artisan('mcp:install --force')
        ->expectsOutputToContain('never overwritten')
        ->assertSuccessful();

    expect(File::get(config_path('mcp-kit.php')))->toContain("'edited' => true")
        ->and(File::get((string) config('mcp-kit.docs.path')))->toContain('550 lines of hand-written docs');
});

/**
 * mcp:install never overwrites a file that exists — it ate a hand-written
 * docs page once — which left the inventory guard recommending a command
 * that could no longer re-pin it.
 */
test('mcp:inventory re-pins the snapshot the guard compares against', function () {
    $path = app(ToolReference::class)->inventoryPath();

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, json_encode(['acme' => ['list_things']], JSON_PRETTY_PRINT)."\n");

    $this->artisan('mcp:inventory', ['--check' => true])->assertExitCode(1);
    $this->artisan('mcp:inventory')->assertExitCode(0);

    $pinned = json_decode((string) file_get_contents($path), true);

    expect($pinned['acme'])->toBe(app(ToolReference::class)->inventory()['acme'])
        ->and($pinned['acme'])->toContain('list_things');

    $this->artisan('mcp:inventory', ['--check' => true])->assertExitCode(0);

    unlink($path);
});
