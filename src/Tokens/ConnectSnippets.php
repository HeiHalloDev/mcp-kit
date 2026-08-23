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
     * @param  list<string>  $serverKeys
     */
    public function claudeCode(array $serverKeys, string $token): string
    {
        $lines = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $lines[] = sprintf(
                'claude mcp add %s --scope user --transport http %s --header "Authorization: Bearer %s"',
                $server->clientName,
                $server->url(),
                $token,
            );
        }

        return implode("\n", $lines);
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
     */
    public function codex(array $serverKeys, string $token): string
    {
        $lines = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $lines[] = sprintf('codex mcp add %s --url %s --bearer-token "%s"', $server->clientName, $server->url(), $token);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $serverKeys
     */
    public function codexToml(array $serverKeys, string $token): string
    {
        $blocks = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $blocks[] = sprintf(
                "[mcp_servers.%s]\nurl = \"%s\"\nbearer_token = \"%s\"",
                str_replace('-', '_', $server->clientName),
                $server->url(),
                $token,
            );
        }

        return implode("\n\n", $blocks);
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
