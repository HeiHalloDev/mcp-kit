<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Docs;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\DocsRenderer;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use ReflectionClass;

/**
 * The tool reference: every tool on every server with its domain, ability
 * and annotations. Rendered into the block between the markers of the docs
 * page; read by the guards for the inventory and the ability checks.
 */
class ToolReference
{
    public const START_MARKER = '<!-- generated:tools:start -->';

    public const END_MARKER = '<!-- generated:tools:end -->';

    public const ABILITIES_START_MARKER = '<!-- generated:abilities:start -->';

    public const ABILITIES_END_MARKER = '<!-- generated:abilities:end -->';

    public function __construct(
        protected ServerRegistry $servers,
        protected AbilityCatalogue $catalogue,
        protected DocsRenderer $renderer,
    ) {}

    public function path(): string
    {
        return $this->absolute((string) config('mcp-kit.docs.path', 'docs/mcp/tools/index.md'));
    }

    public function inventoryPath(): string
    {
        return $this->absolute((string) config('mcp-kit.docs.inventory', 'tests/Feature/Mcp/tool-inventory.json'));
    }

    protected function absolute(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    public function render(string $document): string
    {
        $document = $this->replaceBlock($document, self::START_MARKER, self::END_MARKER, $this->renderer->toolsBlock($this->tools()), append: true);

        if (str_contains($document, self::ABILITIES_START_MARKER) && str_contains($document, self::ABILITIES_END_MARKER)) {
            $document = $this->replaceBlock($document, self::ABILITIES_START_MARKER, self::ABILITIES_END_MARKER, $this->renderer->abilitiesBlock(), append: false);
        }

        return $document;
    }

    protected function replaceBlock(string $document, string $startMarker, string $endMarker, string $body, bool $append): string
    {
        $block = $startMarker."\n\n".rtrim($body)."\n".$endMarker;

        $start = strpos($document, $startMarker);
        $end = strpos($document, $endMarker);

        if ($start === false || $end === false || $end < $start) {
            return $append ? rtrim($document)."\n\n".$block."\n" : $document;
        }

        return substr($document, 0, $start).$block.substr($document, $end + strlen($endMarker));
    }

    /**
     * Every tool class registered on a server, flattening grouped
     * catalogues (`ToolSearch::class => [...]`). Reads the default
     * properties without instantiating the server.
     *
     * @param  class-string  $server
     * @return list<class-string>
     */
    public function toolClassesFor(string $server): array
    {
        if (! class_exists($server)) {
            return [];
        }

        $registered = (new ReflectionClass($server))->getDefaultProperties()['tools'] ?? [];
        $classes = [];

        foreach ((array) $registered as $value) {
            if (is_array($value)) {
                array_push($classes, ...array_values($value));
            } else {
                $classes[] = $value;
            }
        }

        return array_values(array_unique(array_filter($classes, 'is_string')));
    }

    /**
     * @return list<class-string>
     */
    public function toolClasses(): array
    {
        $classes = [];

        foreach ($this->servers->all() as $definition) {
            if ($definition->aliasOf !== null) {
                continue;
            }

            array_push($classes, ...$this->toolClassesFor($definition->class));
        }

        return array_values(array_unique($classes));
    }

    /**
     * Tool names per server, sorted — the inventory snapshot shape.
     *
     * @return array<string, list<string>>
     */
    public function inventory(): array
    {
        $inventory = [];

        foreach ($this->servers->all() as $key => $definition) {
            if ($definition->aliasOf !== null) {
                continue;
            }

            $names = array_map(fn (string $class): string => $this->toolName($class), $this->toolClassesFor($definition->class));
            sort($names);
            $inventory[$key] = array_values($names);
        }

        return $inventory;
    }

    /**
     * @param  class-string  $class
     */
    public function toolName(string $class): string
    {
        $reflection = new ReflectionClass($class);
        $name = $reflection->getDefaultProperties()['name'] ?? '';

        if (is_string($name) && $name !== '') {
            return $name;
        }

        foreach ($reflection->getAttributes(Name::class) as $attribute) {
            return (string) $attribute->newInstance()->value;
        }

        return Str::kebab($reflection->getShortName());
    }

    /**
     * @return list<array{name: string, server: string, domain: string, ability: ?string, writes: bool, annotations: list<string>, description: string, class: class-string}>
     */
    public function tools(): array
    {
        $tools = [];

        foreach ($this->servers->all() as $serverKey => $definition) {
            if ($definition->aliasOf !== null) {
                continue;
            }

            foreach ($this->toolClassesFor($definition->class) as $class) {
                $reflection = new ReflectionClass($class);
                $defaults = $reflection->getDefaultProperties();

                $annotations = [];
                foreach ([IsReadOnly::class, IsIdempotent::class, IsOpenWorld::class, IsDestructive::class] as $annotation) {
                    if ($reflection->getAttributes($annotation) !== []) {
                        $annotations[] = class_basename($annotation);
                    }
                }

                $abilities = $this->abilitiesFor($class);

                $tools[] = [
                    'name' => $this->toolName($class),
                    'server' => $serverKey,
                    'domain' => $this->domainFor($reflection),
                    'ability' => $abilities === [] ? null : implode(' / ', $abilities),
                    'writes' => ! in_array('IsReadOnly', $annotations, true),
                    'annotations' => $annotations,
                    'description' => (string) ($defaults['description'] ?? ''),
                    'class' => $class,
                ];
            }
        }

        usort($tools, static fn (array $a, array $b): int => [$a['server'], $a['domain'], $a['name']] <=> [$b['server'], $b['domain'], $b['name']]);

        return $tools;
    }

    /**
     * The namespace below {App}\Mcp\Tools, or General.
     */
    protected function domainFor(ReflectionClass $reflection): string
    {
        $namespace = $reflection->getNamespaceName();
        $position = strpos($namespace, 'Mcp\\Tools');

        if ($position === false) {
            return 'General';
        }

        $relative = trim(substr($namespace, $position + strlen('Mcp\\Tools')), '\\');

        return $relative === '' ? 'General' : str_replace('\\', '/', $relative);
    }

    /**
     * Every ability a tool can require — found by scanning the source for
     * ability literals with a catalogue prefix, which catches both the
     * usual inline check and tools that choose an ability at runtime.
     *
     * @param  class-string  $tool
     * @return list<string>
     */
    public function abilitiesFor(string $tool): array
    {
        $prefixes = $this->catalogue->prefixes();

        if ($prefixes === []) {
            return [];
        }

        $pattern = sprintf(
            "/['\"]((?:%s):(?:\\*|[a-z0-9_-]+(?::[a-z0-9_*-]+)*))['\"]/",
            implode('|', array_map('preg_quote', $prefixes)),
        );

        $abilities = [];
        $reflection = new ReflectionClass($tool);

        // A tool may inherit its check from an abstract base.
        while ($reflection && $reflection->getName() !== Tool::class) {
            $file = $reflection->getFileName();

            if ($file && is_readable($file)) {
                preg_match_all($pattern, (string) file_get_contents($file), $matches);
                array_push($abilities, ...($matches[1] ?? []));
            }

            $reflection = $reflection->getParentClass() ?: null;
        }

        $abilities = array_values(array_unique($abilities));
        sort($abilities);

        return $abilities;
    }
}
