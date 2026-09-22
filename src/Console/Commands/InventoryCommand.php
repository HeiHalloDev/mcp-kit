<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Console\Commands;

use HeiHallo\McpKit\Docs\ToolReference;
use Illuminate\Console\Command;

/**
 * Re-pins the tool inventory the guard test compares against.
 *
 * `mcp:install` writes it once and then never touches it again — it does
 * not overwrite a file that exists, on purpose, after --force ate a
 * hand-written docs page and a live server config. That left the guard
 * recommending a command that could no longer do the job, and the only way
 * out was editing JSON by hand. This does that one file and nothing else.
 *
 * The snapshot holds each tool's parameter names as well as its name: a
 * tool can gain a path that reaches further than anything it reached
 * yesterday without its name changing, and names alone stay green through
 * that. An older snapshot pinned by name is still honoured; an app opts in
 * by running this.
 */
class InventoryCommand extends Command
{
    protected $signature = 'mcp:inventory {--check : Say whether it is current, change nothing}';

    protected $description = 'Re-pin the registered MCP tools and the parameters they take';

    public function handle(ToolReference $tools): int
    {
        $path = $tools->inventoryPath();
        $relative = str_replace(base_path().'/', '', $path);
        $inventory = $tools->surface();
        $json = json_encode($inventory === [] ? (object) [] : $inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $pinned = is_file($path) ? (string) file_get_contents($path) : null;

        if ($pinned === $json) {
            $this->components->info("{$relative} is current.");

            return self::SUCCESS;
        }

        if ($this->option('check')) {
            $this->components->error("{$relative} is out of date. Run php artisan mcp:inventory.");

            return self::FAILURE;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $json);

        $parameters = 0;

        foreach ($inventory as $tools) {
            $parameters += array_sum(array_map('count', $tools));
        }

        $this->components->info(sprintf(
            '%s%s: %d tools, %d parameters.',
            $pinned === null ? 'Wrote ' : 'Re-pinned ',
            $relative,
            array_sum(array_map('count', $inventory)),
            $parameters,
        ));

        return self::SUCCESS;
    }
}
