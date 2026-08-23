<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools\Concerns;

/**
 * Publish the tool's `$inputSchema` to MCP clients.
 *
 * Many tools declare their parameters as a hand-written JSON Schema in a
 * `$inputSchema` property. laravel/mcp builds the advertised schema from a
 * `schema(JsonSchema $schema)` method instead and ignores the property, so
 * without this bridge every such tool advertises `{"type":"object","properties":{}}`
 * and clients have to guess arguments from prose (arrays arrive as strings,
 * names get misspelled). This splices the array into the advertised payload;
 * `$inputSchema` stays the single place parameters are declared.
 */
trait DeclaresInputSchema
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = parent::toArray();
        $schema = $this->resolveInputSchema();

        if ($schema !== []) {
            $schema['type'] ??= 'object';

            // An empty PHP array would serialise as [] — not a valid JSON Schema
            // object — and clients drop the whole server's tools over one bad
            // schema, so a tool without parameters must advertise {}.
            if (empty($schema['properties'])) {
                $schema['properties'] = (object) [];
            }

            $payload['inputSchema'] = $schema;
        }

        return $payload;
    }

    /**
     * Tools whose schema depends on runtime data override this instead of
     * the property.
     *
     * @return array<string, mixed>
     */
    protected function resolveInputSchema(): array
    {
        return property_exists($this, 'inputSchema') ? $this->inputSchema : [];
    }
}
