<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Console\Commands;

use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Servers\StaffServer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Scaffolds what an app needs to run the kit: the config, a server class,
 * the ground-rules intro, the guard test and its inventory, the docs page.
 * Re-runnable; deletes nothing — an existing file is never rewritten, not
 * even with --force: once installed, these files carry the app's own
 * content (the catalogue in the config, the hand-written docs around the
 * markers), and a scaffold has no way to tell that apart from its own
 * leftovers. Re-scaffolding one file is an explicit act: delete it, re-run.
 */
class InstallCommand extends Command
{
    protected $signature = 'mcp:install
                            {--scan : Read the abilities the existing tools check and write catalogue entries for them}
                            {--force : Kept for old scripts; existing files are still never overwritten}
                            {--with-tokens-page : Turn the tokens page on in the published config}';

    protected $description = 'Scaffold mcp-kit into this app: config, server, ground rules, guard test, docs';

    public function handle(): int
    {
        $this->publishConfig();
        $this->writeServer();
        $this->writeGroundRulesIntro();
        $this->writeGuardTest();
        $this->writePestActors();
        $this->writeDocs();
        $this->writeInventory();

        if ($this->option('scan')) {
            $this->scanAbilities();
        }

        if ($this->option('with-tokens-page')) {
            $this->enableTokensPage();
        }

        $this->warnAboutLegacyReadOnly();

        $this->newLine();
        $this->components->info('Done. Next:');
        $this->line('  1. Fill in config/mcp-kit.php: servers, catalogue.abilities, permission_rules.');
        $this->line('  2. php artisan migrate');
        $this->line('  3. php artisan mcp:token you@example.com');
        $this->line('  4. php artisan mcp:docs && vendor/bin/pest tests/Feature/Mcp');

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $target = config_path('mcp-kit.php');

        if (is_file($target)) {
            $this->components->twoColumnDetail('config/mcp-kit.php', $this->keptMessage());

            return;
        }

        copy(__DIR__.'/../../../config/mcp-kit.php', $target);
        $this->components->twoColumnDetail('config/mcp-kit.php', 'written');
    }

    protected function writeServer(): void
    {
        $directory = app_path('Mcp/Servers');

        if (is_dir($directory)) {
            foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
                $class = $this->classFromFile($file->getRealPath());

                if ($class !== null && class_exists($class) && is_subclass_of($class, StaffServer::class)) {
                    $this->components->twoColumnDetail('server', class_basename($class).' extends StaffServer');

                    return;
                }
            }
        }

        $class = Str::studly((string) config('app.name', 'App')).'Server';
        $class = preg_replace('/[^A-Za-z0-9]/', '', $class) ?: 'AppServer';
        $path = "{$directory}/{$class}.php";

        if (is_file($path)) {
            $this->components->twoColumnDetail("app/Mcp/Servers/{$class}.php", 'exists (does not extend StaffServer — change the parent)');

            return;
        }

        $this->write($path, $this->stub('server', [
            'class' => $class,
            'label' => (string) config('app.name', 'App'),
        ]));

        $this->components->twoColumnDetail("app/Mcp/Servers/{$class}.php", 'written');
        $this->line('    Register it under mcp-kit.servers in config/mcp-kit.php.');
    }

    protected function writeGroundRulesIntro(): void
    {
        $path = resource_path('views/vendor/mcp-kit/ground-rules/intro.blade.php');

        if (is_file($path)) {
            $this->components->twoColumnDetail('resources/views/vendor/mcp-kit/ground-rules/intro.blade.php', $this->keptMessage());

            return;
        }

        $this->write($path, $this->stub('ground-rules-intro'));
        $this->components->twoColumnDetail('resources/views/vendor/mcp-kit/ground-rules/intro.blade.php', 'written');
    }

    protected function writeGuardTest(): void
    {
        $path = base_path('tests/Feature/Mcp/KitGuardsTest.php');

        if (is_file($path)) {
            $this->components->twoColumnDetail('tests/Feature/Mcp/KitGuardsTest.php', $this->keptMessage());

            return;
        }

        $this->write($path, $this->stub('guards-test'));
        $this->components->twoColumnDetail('tests/Feature/Mcp/KitGuardsTest.php', 'written');
    }

    protected function writePestActors(): void
    {
        $path = base_path('tests/Pest.php');

        if (! is_file($path)) {
            $this->components->twoColumnDetail('tests/Pest.php', 'missing — add Guards::actors(...) yourself');

            return;
        }

        $contents = (string) file_get_contents($path);

        if (str_contains($contents, 'Guards::actors(')) {
            $this->components->twoColumnDetail('tests/Pest.php', 'actors registered');

            return;
        }

        file_put_contents($path, rtrim($contents)."\n".$this->stub('pest-actors'));
        $this->components->twoColumnDetail('tests/Pest.php', 'Guards::actors(...) appended — fill in the TODOs');
    }

    protected function writeDocs(): void
    {
        $path = app(ToolReference::class)->path();
        $relative = str_replace(base_path().'/', '', $path);

        if (is_file($path)) {
            $contents = (string) file_get_contents($path);

            if (! str_contains($contents, '<!-- generated:tools:start -->')) {
                file_put_contents($path, rtrim($contents)."\n\n<!-- generated:tools:start -->\n<!-- generated:tools:end -->\n");
                $this->components->twoColumnDetail($relative, 'markers appended');

                return;
            }

            $this->components->twoColumnDetail($relative, $this->keptMessage());

            return;
        }

        $this->write($path, $this->stub('tools-index'));
        $this->components->twoColumnDetail($relative, 'written');
    }

    protected function writeInventory(): void
    {
        $path = app(ToolReference::class)->inventoryPath();
        $relative = str_replace(base_path().'/', '', $path);

        if (is_file($path)) {
            $this->components->twoColumnDetail($relative, $this->keptMessage());

            return;
        }

        $inventory = app(ToolReference::class)->inventory();

        $this->write($path, json_encode($inventory === [] ? (object) [] : $inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->components->twoColumnDetail($relative, 'written');
    }

    /**
     * Reads every 'prefix:…' literal the tools under app/Mcp/Tools check and
     * adds catalogue entries for the unknown ones, with a TODO description
     * and no permission, straight into the published config.
     */
    protected function scanAbilities(): void
    {
        $directory = app_path('Mcp/Tools');

        if (! is_dir($directory)) {
            $this->components->twoColumnDetail('scan', 'no app/Mcp/Tools directory');

            return;
        }

        $found = [];

        foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
            preg_match_all("/['\"]([a-z][a-z0-9-]*:(?:[a-z0-9_-]+)(?::[a-z0-9_-]+)*)['\"]/", $file->getContents(), $matches);

            foreach ($matches[1] ?? [] as $ability) {
                if (str_ends_with($ability, ':*') || preg_match('/^(https?|file|app):/', $ability)) {
                    continue;
                }

                $found[$ability] = true;
            }
        }

        $known = array_keys((array) config('mcp-kit.catalogue.abilities', []));
        $new = array_values(array_diff(array_keys($found), $known));
        sort($new);

        if ($new === []) {
            $this->components->twoColumnDetail('scan', 'every checked ability is already in the catalogue');

            return;
        }

        $path = config_path('mcp-kit.php');
        $contents = (string) file_get_contents($path);
        $firstServer = array_key_first((array) config('mcp-kit.servers', [])) ?? 'app';

        $entries = '';

        foreach ($new as $ability) {
            $entries .= sprintf(
                "            '%s' => ['TODO describe', null, '%s'],\n",
                $ability,
                $firstServer,
            );
        }

        $updated = preg_replace(
            "/('abilities'\s*=>\s*\[)(\s*)/",
            "$1\n".$entries.'$2',
            $contents,
            1,
        );

        if ($updated === null || $updated === $contents) {
            $this->components->twoColumnDetail('scan', 'could not find catalogue.abilities in config/mcp-kit.php — add these yourself: '.implode(', ', $new));

            return;
        }

        file_put_contents($path, $updated);
        $this->components->twoColumnDetail('scan', count($new).' abilities added to config/mcp-kit.php (fill in descriptions and permissions)');
    }

    protected function enableTokensPage(): void
    {
        $path = config_path('mcp-kit.php');
        $contents = (string) file_get_contents($path);

        $updated = preg_replace(
            "/'enabled' => \(bool\) env\('MCP_KIT_TOKENS_PAGE', false\)/",
            "'enabled' => (bool) env('MCP_KIT_TOKENS_PAGE', true)",
            $contents,
            1,
        );

        $updated = preg_replace(
            "/'enabled' => \(bool\) env\('MCP_KIT_UI', false\)/",
            "'enabled' => (bool) env('MCP_KIT_UI', true)",
            (string) $updated,
            1,
        );

        if ($updated !== null && $updated !== $contents) {
            file_put_contents($path, $updated);
            $this->components->twoColumnDetail('tokens page', 'enabled in config/mcp-kit.php (ui.enabled, ui.tokens_page.enabled)');
        } else {
            $this->components->twoColumnDetail('tokens page', 'set mcp-kit.ui.enabled and ui.tokens_page.enabled to true yourself');
        }
    }

    /**
     * What an existing file is told: with --force, why it still stands.
     */
    protected function keptMessage(): string
    {
        return $this->option('force')
            ? 'exists — never overwritten, --force or not; delete it and re-run for a fresh scaffold'
            : 'exists';
    }

    protected function warnAboutLegacyReadOnly(): void
    {
        if (config()->has('mcp.read_only')) {
            $this->components->warn('config/mcp.php still has read_only — the kit reads mcp-kit.read_only (MCP_READ_ONLY). Remove the old key.');
        }
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function stub(string $name, array $replacements = []): string
    {
        $contents = (string) file_get_contents(__DIR__."/../../../stubs/{$name}.stub");

        $replacements = [
            'namespace' => rtrim($this->laravel->getNamespace(), '\\'),
            'app' => (string) config('app.name', 'this app'),
            'scheme' => (string) config('mcp-kit.scheme', 'app'),
            ...$replacements,
        ];

        foreach ($replacements as $key => $value) {
            $contents = str_replace('{{ '.$key.' }}', $value, $contents);
        }

        return $contents;
    }

    protected function write(string $path, string $contents): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);
    }

    protected function classFromFile(string $path): ?string
    {
        $contents = (string) file_get_contents($path);

        if (! preg_match('/^namespace\s+([^;]+);/m', $contents, $ns) || ! preg_match('/^(?:abstract\s+|final\s+)?class\s+(\w+)/m', $contents, $class)) {
            return null;
        }

        return trim($ns[1]).'\\'.$class[1];
    }
}
