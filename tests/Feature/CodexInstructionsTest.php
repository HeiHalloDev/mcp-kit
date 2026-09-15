<?php

declare(strict_types=1);

use HeiHallo\McpKit\Tokens\ConnectSnippets;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The AGENTS.md paste runs on a person's own machine, against a file they may
 * already keep. Run it for real: twice, as somebody pasting it from a second
 * app would, into a file with their own lines in it.
 */
test('the Codex instructions land in AGENTS.md once, however often they are pasted', function () {
    $home = __DIR__.'/../tmp/codex-home-'.Str::random(8);
    File::ensureDirectoryExists($home.'/.codex');
    File::put($home.'/.codex/AGENTS.md', "# My own rules\nKeep answers short.\n");

    $command = app(ConnectSnippets::class)->codexInstructionsTerminal();

    try {
        foreach ([1, 2] as $run) {
            exec('HOME='.escapeshellarg($home).' bash -c '.escapeshellarg($command), result_code: $code);

            expect($code)->toBe(0);
        }

        $written = File::get($home.'/.codex/AGENTS.md');

        expect(substr_count($written, '<!-- mcp-kit -->'))->toBe(1)
            ->and(substr_count($written, '<!-- /mcp-kit -->'))->toBe(1)
            ->and($written)->toContain('Keep answers short.')
            ->and($written)->toContain('call `working_on`')
            ->and($written)->toContain('call `report_gap`');
    } finally {
        File::deleteDirectory($home);
    }
});

test('the paste creates the file when Codex has none yet', function () {
    $home = __DIR__.'/../tmp/codex-home-'.Str::random(8);
    File::ensureDirectoryExists($home);

    try {
        exec('HOME='.escapeshellarg($home).' bash -c '.escapeshellarg(app(ConnectSnippets::class)->codexInstructionsTerminal()), result_code: $code);

        expect($code)->toBe(0)
            ->and(File::get($home.'/.codex/AGENTS.md'))->toStartWith('<!-- mcp-kit -->');
    } finally {
        File::deleteDirectory($home);
    }
});
