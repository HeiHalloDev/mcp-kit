<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Servers;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use ReflectionClass;

/**
 * Strips every tool that is not annotated #[IsReadOnly]. Filters recursively
 * and keeps string keys: laravel/mcp allows grouped tools
 * (`ToolSearch::class => [...]`) and requires the group to stay under its
 * key, so an array_values() here would silently break that.
 */
final class ReadOnlyFilter
{
    /**
     * @param  array<int|string, mixed>  $tools
     * @return array<int|string, mixed>
     */
    public static function apply(array $tools): array
    {
        $filtered = [];

        foreach ($tools as $key => $tool) {
            if (is_array($tool)) {
                $nested = self::apply($tool);

                if ($nested !== []) {
                    $filtered[$key] = $nested;
                }

                continue;
            }

            $class = is_object($tool) ? $tool::class : $tool;

            if (! class_exists($class) || (new ReflectionClass($class))->getAttributes(IsReadOnly::class) === []) {
                continue;
            }

            if (is_int($key)) {
                $filtered[] = $tool;
            } else {
                $filtered[$key] = $tool;
            }
        }

        return $filtered;
    }
}
