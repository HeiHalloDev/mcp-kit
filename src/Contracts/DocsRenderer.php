<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Contracts;

interface DocsRenderer
{
    /**
     * The markdown between the generated:tools markers.
     *
     * @param  list<array{name: string, server: string, domain: string, ability: ?string, writes: bool, annotations: list<string>, description: string, class: class-string}>  $tools
     */
    public function toolsBlock(array $tools): string;

    /**
     * The markdown between the generated:abilities markers (rendered only
     * when the document has them).
     */
    public function abilitiesBlock(): string;
}
