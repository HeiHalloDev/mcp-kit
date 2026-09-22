<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tokens;

/**
 * How to get the client itself, before any of the lines on the Connect tab
 * mean anything.
 *
 * The page handed people `claude mcp add …` and assumed `claude` was on
 * their machine. It is not: the people minting these tokens are staff who
 * were told an assistant could read the CRM, not developers with a shell
 * already open. Where to download it and the one line that installs it are
 * part of connecting, and they are the same for every app on the kit — so
 * they live here and not in ten app repos drifting apart.
 *
 * Every link and command below was checked on 2026-09-22 against the
 * vendors' own documentation, which is linked with each one so a reader
 * can see whether it has moved since.
 */
final class ClientSetup
{
    /**
     * @return array<string, array{title: string, summary: string, download: ?array{label: string, url: string}, install: array<string, string>, verify: ?string, docs: array{label: string, url: string}}>
     */
    public static function all(): array
    {
        return [
            'claude-code' => self::claudeCode(),
            'claude-desktop' => self::claudeDesktop(),
            'codex' => self::codex(),
        ];
    }

    /**
     * @return array{title: string, summary: string, download: ?array{label: string, url: string}, install: array<string, string>, verify: ?string, docs: array{label: string, url: string}}
     */
    public static function claudeCode(): array
    {
        return [
            'title' => __('Claude Code'),
            'summary' => __('Claude in a terminal. It needs a paid Claude account — Pro, Max, Team or Enterprise; the free plan does not include it. Prefer not to use a terminal at all? The Claude desktop app has Claude Code built in.'),
            'download' => ['label' => __('Claude for Mac and Windows'), 'url' => 'https://claude.com/download'],
            'install' => [
                __('macOS, Linux, WSL') => 'curl -fsSL https://claude.ai/install.sh | bash',
                __('Windows PowerShell') => 'irm https://claude.ai/install.ps1 | iex',
                __('Homebrew') => 'brew install --cask claude-code',
                __('npm') => 'npm install -g @anthropic-ai/claude-code',
            ],
            'verify' => 'claude --version',
            'docs' => ['label' => __('Installing Claude Code'), 'url' => 'https://code.claude.com/docs/en/setup'],
        ];
    }

    /**
     * @return array{title: string, summary: string, download: ?array{label: string, url: string}, install: array<string, string>, verify: ?string, docs: array{label: string, url: string}}
     */
    public static function claudeDesktop(): array
    {
        return [
            'title' => __('Claude Desktop'),
            'summary' => __('The Claude app for Mac and Windows. It has two sides, and they read different settings: the Code tab is Claude Code and picks up anything added with the claude mcp add line on the Claude Code tab — one paste, nothing to edit. The configuration below is for the chat side.'),
            'download' => ['label' => __('Download Claude'), 'url' => 'https://claude.com/download'],
            'install' => [
                __('Homebrew') => 'brew install --cask claude',
            ],
            'verify' => null,
            'docs' => ['label' => __('Claude Code in the desktop app'), 'url' => 'https://code.claude.com/docs/en/desktop-quickstart'],
        ];
    }

    /**
     * @return array{title: string, summary: string, download: ?array{label: string, url: string}, install: array<string, string>, verify: ?string, docs: array{label: string, url: string}}
     */
    public static function codex(): array
    {
        return [
            'title' => __('Codex'),
            'summary' => __('OpenAI\'s assistant, in the ChatGPT app, in a terminal or in an editor. All three read the same ~/.codex/config.toml, so connecting once from any of them connects the rest. It needs a paid ChatGPT account.'),
            'download' => ['label' => __('Download ChatGPT'), 'url' => 'https://chatgpt.com/download'],
            'install' => [
                __('macOS, Linux') => 'curl -fsSL https://chatgpt.com/codex/install.sh | sh',
                __('Homebrew') => 'brew install --cask codex',
                __('npm') => 'npm install -g @openai/codex',
            ],
            'verify' => 'codex --version',
            'docs' => ['label' => __('Installing Codex'), 'url' => 'https://learn.chatgpt.com/docs/codex/cli'],
        ];
    }
}
