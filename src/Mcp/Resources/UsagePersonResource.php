<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Support\UriTemplate;

/**
 * The usage record for one person: "how has Kine used this lately" in one
 * read, instead of the whole record or a query against production. The
 * person is part of a name or a user id. Registered wherever the usage
 * resource is, so an app that published its config gets it too.
 */
class UsagePersonResource extends UsageResource implements HasUriTemplate
{
    protected string $name = 'usage-person';

    protected string $description = 'For whoever builds this app: the usage record for one person — part of their name or their user id — with when each piece of work happened and, where nobody named it, which tools it used. Privileged staff only.';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate(config('mcp-kit.scheme', 'app').'://usage/{person}');
    }

    public function uri(): string
    {
        return (string) $this->uriTemplate();
    }

    protected function person(Request $request): ?string
    {
        $person = rawurldecode((string) $request->get('person', ''));

        return trim($person) === '' ? null : $person;
    }
}
