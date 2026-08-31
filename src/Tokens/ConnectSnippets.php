<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tokens;

use HeiHallo\McpKit\Servers\ServerDefinition;
use HeiHallo\McpKit\Servers\ServerRegistry;

/**
 * The lines a person pastes to connect a client, one per server the token
 * reaches. The command prints the Claude Code form; the tokens page shows
 * all of them.
 */
class ConnectSnippets
{
    public function __construct(protected ServerRegistry $servers) {}

    /**
     * @param  list<string>  $serverKeys
     * @return list<ServerDefinition>
     */
    protected function definitions(array $serverKeys): array
    {
        return array_values(array_filter(array_map(fn (string $key): ?ServerDefinition => $this->servers->get($key), $serverKeys)));
    }

    /**
     * One line per server, keyed by the name the client will know it as, so a
     * page can offer them one at a time as well as all at once.
     *
     * @param  list<string>  $serverKeys
     * @return array<string, string>
     */
    public function claudeCodeLines(array $serverKeys, string $token): array
    {
        $lines = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $lines[$server->clientName] = sprintf(
                'claude mcp add %s --scope user --transport http %s --header "Authorization: Bearer %s"',
                $server->clientName,
                $server->url(),
                $token,
            );
        }

        return $lines;
    }

    /**
     * @param  list<string>  $serverKeys
     */
    public function claudeCode(array $serverKeys, string $token): string
    {
        return implode("\n", $this->claudeCodeLines($serverKeys, $token));
    }

    /**
     * Undo for the lines above. Removal is by name, so it carries no token and
     * is safe to show whether or not a token has just been minted.
     *
     * @param  list<string>  $serverKeys
     * @return array<string, string>
     */
    public function claudeCodeRemoveLines(array $serverKeys): array
    {
        $lines = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $lines[$server->clientName] = sprintf('claude mcp remove %s', $server->clientName);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $serverKeys
     */
    public function claudeCodeRemove(array $serverKeys): string
    {
        return implode("\n", $this->claudeCodeRemoveLines($serverKeys));
    }

    /**
     * @param  list<string>  $serverKeys
     */
    public function claudeDesktop(array $serverKeys, string $token): string
    {
        $servers = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $servers[$server->clientName] = [
                'type' => 'http',
                'url' => $server->url(),
                'headers' => ['Authorization' => "Bearer {$token}"],
            ];
        }

        return (string) json_encode(['mcpServers' => $servers === [] ? (object) [] : $servers], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  list<string>  $serverKeys
     * @return array<string, string>
     */
    public function codexLines(array $serverKeys, string $token): array
    {
        $lines = [];

        // Codex never takes the token itself — only the NAME of an env var
        // it reads at runtime (--bearer-token-env-var). So every snippet is
        // two lines: put the token in the environment, then point Codex at
        // it. The export belongs in the shell profile to survive new
        // terminals; the snippet works as pasted either way.
        foreach ($this->definitions($serverKeys) as $server) {
            $lines[$server->clientName] = sprintf(
                "# add this export to ~/.zshrc (or your shell profile) — Codex reads it in every new terminal\nexport %s=\"%s\"\ncodex mcp add %s --url %s --bearer-token-env-var %s",
                $this->envVar($server->clientName),
                $token,
                $server->clientName,
                $server->url(),
                $this->envVar($server->clientName),
            );
        }

        return $lines;
    }

    /**
     * The env var a server's token lives in for Codex: CRM_MCP_TOKEN,
     * CRM_REPORTS_MCP_TOKEN.
     */
    public function envVar(string $clientName): string
    {
        return strtoupper(str_replace('-', '_', $clientName)).'_MCP_TOKEN';
    }

    /**
     * @param  list<string>  $serverKeys
     */
    public function codex(array $serverKeys, string $token): string
    {
        return implode("\n", $this->codexLines($serverKeys, $token));
    }

    /**
     * @param  list<string>  $serverKeys
     * @return array<string, string>
     */
    public function codexRemoveLines(array $serverKeys): array
    {
        $lines = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $lines[$server->clientName] = sprintf('codex mcp remove %s', $server->clientName);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $serverKeys
     */
    public function codexRemove(array $serverKeys): string
    {
        return implode("\n", $this->codexRemoveLines($serverKeys));
    }

    /**
     * @param  list<string>  $serverKeys
     */
    public function codexToml(array $serverKeys, string $token): string
    {
        $blocks = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $blocks[] = sprintf(
                "[mcp_servers.%s]\nurl = \"%s\"\nbearer_token_env_var = \"%s\"",
                str_replace('-', '_', $server->clientName),
                $server->url(),
                $this->envVar($server->clientName),
            );
        }

        // The TOML names the env var too, so the exports still have to
        // exist — say so where the block is pasted from.
        return "# Codex reads the token from the environment: add the export lines\n# from the tab above to ~/.zshrc (or your shell profile) first.\n\n".implode("\n\n", $blocks);
    }

    /**
     * @param  list<string>  $serverKeys
     */
    public function curl(array $serverKeys, string $token): string
    {
        $server = $this->definitions($serverKeys)[0] ?? null;

        if ($server === null) {
            return '';
        }

        return sprintf(
            "curl -X POST %s \\\n  -H \"Authorization: Bearer %s\" \\\n  -H \"Content-Type: application/json\" \\\n  -H \"Accept: application/json, text/event-stream\" \\\n  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'",
            $server->url(),
            $token,
        );
    }
}
