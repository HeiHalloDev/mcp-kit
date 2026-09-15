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
     * One TOML block per server, keyed by the name the client will know it
     * as. The token goes into ~/.codex/config.toml as a static header
     * (http_headers) — the terminal, the ChatGPT app and the IDE extension
     * all read that file, and only the terminal ever sees shell env vars,
     * so a bearer_token_env_var export never reaches the app. There is no
     * CLI flag for http_headers, so the snippet is the block itself.
     *
     * @param  list<string>  $serverKeys
     * @return array<string, string>
     */
    public function codexLines(array $serverKeys, string $token): array
    {
        $lines = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $lines[$server->clientName] = sprintf(
                "[mcp_servers.%s]\nurl = \"%s\"\nhttp_headers = { Authorization = \"Bearer %s\" }",
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
    public function codex(array $serverKeys, string $token): string
    {
        return implode("\n\n", $this->codexLines($serverKeys, $token));
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
        return "# append to ~/.codex/config.toml — the terminal, the ChatGPT app and\n# the IDE extension all read this file. No env vars, no exports.\n\n".$this->codex($serverKeys, $token);
    }

    /**
     * The values to type into the ChatGPT app's own form (Plugins → MCPs),
     * one block per server — for people who have never opened a terminal.
     * The form has no field for the token itself, only for the NAME of an
     * env var — which the app cannot read — so the token goes in as a
     * static header row instead ("Headers", not "Headers from environment
     * variables"). That row is http_headers, the mechanism the terminal
     * route uses too.
     *
     * Field by field, so the page can give every value its own copy button:
     * the form takes them one at a time.
     *
     * @param  list<string>  $serverKeys
     * @return array<string, array<string, string>>
     */
    public function codexAppFields(array $serverKeys, string $token): array
    {
        $fields = [];

        foreach ($this->definitions($serverKeys) as $server) {
            $fields[$server->clientName] = [
                'Name' => $server->clientName,
                'Type' => 'Streamable HTTP',
                'URL' => $server->url(),
                'Header key' => 'Authorization',
                'Header value' => "Bearer {$token}",
            ];
        }

        return $fields;
    }

    /**
     * The same values as one aligned text block per server.
     *
     * @param  list<string>  $serverKeys
     * @return array<string, string>
     */
    public function codexAppLines(array $serverKeys, string $token): array
    {
        return array_map(
            fn (array $fields): string => implode("\n", array_map(
                fn (string $label, string $value): string => str_pad("{$label}:", 15).$value,
                array_keys($fields),
                $fields,
            )),
            $this->codexAppFields($serverKeys, $token),
        );
    }

    /**
     * One paste-and-enter terminal command that writes every server into
     * ~/.codex/config.toml. The awk pass first drops any existing entry
     * for these names, so running it again after a token rotation
     * replaces instead of duplicating (a duplicated TOML table breaks the
     * whole file).
     *
     * @param  list<string>  $serverKeys
     */
    public function codexTerminal(array $serverKeys, string $token): string
    {
        $definitions = $this->definitions($serverKeys);

        if ($definitions === []) {
            return '';
        }

        $names = implode('|', array_map(fn (ServerDefinition $server): string => preg_quote($server->clientName, '/'), $definitions));

        return sprintf(
            "mkdir -p ~/.codex && touch ~/.codex/config.toml\nawk '/^\\[mcp_servers\\.(%s)\\]$/{skip=1;next} /^\\[/{skip=0} !skip' ~/.codex/config.toml > ~/.codex/config.toml.new && mv ~/.codex/config.toml.new ~/.codex/config.toml\ncat >> ~/.codex/config.toml <<'TOML'\n%s\nTOML",
            $names,
            $this->codex($serverKeys, $token),
        );
    }

    /**
     * What Codex is told about working_on and report_gap, as it sits in
     * ~/.codex/AGENTS.md — the file Codex reads at the start of every
     * thread, in the terminal, the ChatGPT app and the IDE extension. A
     * long session compacts away what the server said at connect time;
     * this comes back with every new thread.
     *
     * The text is the same from every app and the markers are fixed here,
     * outside the overridable view, so pasting it from a second app
     * replaces the block instead of stacking another copy.
     */
    public function codexInstructions(): string
    {
        return "<!-- mcp-kit -->\n".trim(view('mcp-kit::tokens.codex-instructions')->render())."\n<!-- /mcp-kit -->";
    }

    /**
     * One paste-and-enter terminal command that writes the block above into
     * ~/.codex/AGENTS.md. The awk pass drops an earlier block first and
     * leaves everything else in the file alone.
     */
    public function codexInstructionsTerminal(): string
    {
        return sprintf(
            "mkdir -p ~/.codex && touch ~/.codex/AGENTS.md\nawk '/^<!-- mcp-kit -->$/{skip=1;next} /^<!-- \\/mcp-kit -->$/{skip=0;next} !skip' ~/.codex/AGENTS.md > ~/.codex/AGENTS.md.new && mv ~/.codex/AGENTS.md.new ~/.codex/AGENTS.md\ncat >> ~/.codex/AGENTS.md <<'MD'\n%s\nMD",
            $this->codexInstructions(),
        );
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
