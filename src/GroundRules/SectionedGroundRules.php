<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\GroundRules;

use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Contracts\Section;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\View\Factory as View;

/**
 * The package partials (intro, safety, tokens, names_and_links, replies,
 * memory) followed by the app's mcp-kit.ground_rules.sections, minus
 * mcp-kit.ground_rules.remove. Any partial can be overridden as a vendor
 * view (resources/views/vendor/mcp-kit/ground-rules/{name}.blade.php).
 */
class SectionedGroundRules implements GroundRules
{
    public const PACKAGE_SECTIONS = [
        'intro' => 'What this is',
        'safety' => 'Safety',
        'tokens' => 'People and tokens',
        'names_and_links' => 'Names and links',
        'replies' => 'Replies',
        'memory' => 'The person you are helping',
    ];

    public function __construct(
        protected View $view,
        protected Cache $cache,
        protected ServerRegistry $servers,
    ) {}

    public function sections(?Principal $principal, ?string $server): array
    {
        $remove = array_map('strval', (array) config('mcp-kit.ground_rules.remove', []));
        $sections = [];

        foreach (self::PACKAGE_SECTIONS as $name => $heading) {
            if (in_array($name, $remove, true)) {
                continue;
            }

            $sections[$heading] = trim($this->view->make("mcp-kit::ground-rules.{$name}", $this->data($principal, $server))->render());
        }

        foreach ((array) config('mcp-kit.ground_rules.sections', []) as $heading => $body) {
            if (in_array((string) $heading, $remove, true)) {
                continue;
            }

            $sections[(string) $heading] = trim($this->renderSection($body, $principal, $server));
        }

        return array_filter($sections, static fn (string $body): bool => $body !== '');
    }

    public function text(?Principal $principal, ?string $server): string
    {
        $key = sprintf(
            'mcp-kit:ground-rules:%s:%s:%s',
            $principal?->privileged ? 'privileged' : 'staff',
            $server ?? '-',
            app()->getLocale(),
        );

        return $this->cache->remember($key, 600, function () use ($principal, $server): string {
            $title = config('mcp-kit.ground_rules.title') ?? config('app.name', 'This app').' — ground rules for staff tools';
            $out = "# {$title}\n";

            foreach ($this->sections($principal, $server) as $heading => $body) {
                $out .= "\n## {$heading}\n{$body}\n";
            }

            return $out;
        });
    }

    public function instructions(string $server, string $authored): string
    {
        $authored = rtrim($authored);

        if (! config('mcp-kit.instructions.append_footer', true) || ! ($this->servers->get($server)?->shared ?? true)) {
            return $authored;
        }

        $scheme = (string) config('mcp-kit.scheme', 'app');

        return $authored."\n\nStart a new session by reading `{$scheme}://me` and `{$scheme}://ground-rules`. Writes preview without `confirm=true` and execute with it.";
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(?Principal $principal, ?string $server): array
    {
        return [
            'principal' => $principal,
            'server' => $server !== null ? $this->servers->get($server) : null,
            'servers' => $this->servers->all(),
            'appName' => (string) config('app.name', 'this app'),
            'scheme' => (string) config('mcp-kit.scheme', 'app'),
            'intro' => config('mcp-kit.ground_rules.intro'),
            'explicitOnly' => array_map('strval', (array) config('mcp-kit.catalogue.explicit_only', [])),
            'memoryUrl' => config('mcp-kit.ui.memory_url'),
        ];
    }

    protected function renderSection(mixed $body, ?Principal $principal, ?string $server): string
    {
        if ($body instanceof Section) {
            return $body->render($principal, $server);
        }

        if (is_string($body) && class_exists($body) && is_a($body, Section::class, true)) {
            return app($body)->render($principal, $server);
        }

        if (is_string($body) && str_starts_with($body, 'view:')) {
            return $this->view->make(substr($body, 5), $this->data($principal, $server))->render();
        }

        if (is_callable($body)) {
            return (string) $body($principal, $server);
        }

        return (string) $body;
    }
}
