<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Console\Commands;

use HeiHallo\McpKit\Docs\ToolReference;
use Illuminate\Console\Command;

/**
 * Regenerate the tool reference in the docs page from the tools registered
 * on every server. Prose and frontmatter stay hand-written; only the block
 * between the markers is replaced.
 */
class DocsCommand extends Command
{
    protected $signature = 'mcp:docs {--check : Exit non-zero if the docs are out of date, without writing}';

    protected $description = 'Regenerate the MCP tool reference from the registered tools';

    public function handle(ToolReference $reference): int
    {
        $path = $reference->path();

        if (! is_file($path)) {
            $this->error("Missing {$path}. Run `php artisan mcp:install` to scaffold it.");

            return self::FAILURE;
        }

        $current = (string) file_get_contents($path);
        $expected = $reference->render($current);

        if ($current === $expected) {
            $this->info('MCP docs are up to date.');

            return self::SUCCESS;
        }

        if ($this->option('check')) {
            $this->error('MCP docs are out of date. Run: php artisan mcp:docs');

            return self::FAILURE;
        }

        file_put_contents($path, $expected);
        $this->info('Regenerated '.count($reference->tools()).' tools in '.str_replace(base_path().'/', '', $path).'.');

        return self::SUCCESS;
    }
}
