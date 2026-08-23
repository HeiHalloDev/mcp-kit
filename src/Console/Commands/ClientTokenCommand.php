<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Console\Commands;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Contracts\ServiceClient;
use HeiHallo\McpKit\Models\ServiceClient as ServiceClientModel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tokens for service clients: other systems that read, and write only what
 * mcp-kit.catalogue.service_client_writes allows.
 */
class ClientTokenCommand extends Command
{
    protected $signature = 'mcp:client-token
                            {client : Slug (or name) of the service client}
                            {--create : Create the client if it does not exist}
                            {--description= : Description when creating}
                            {--abilities= : Comma-separated abilities (reads, plus allowed service-client writes)}
                            {--revoke : Revoke every token of the client}
                            {--deactivate : Deactivate the client (its tokens stop working at once)}
                            {--activate : Reactivate the client}';

    protected $description = 'Mint or revoke MCP tokens for a service client (another system, no person behind it)';

    public function handle(AbilityCatalogue $catalogue, AuditWriter $audit): int
    {
        $model = config('mcp-kit.models.service_client');

        if (! is_string($model) || ! class_exists($model)) {
            $this->error('Service clients are disabled (mcp-kit.models.service_client is null).');

            return self::FAILURE;
        }

        $identifier = (string) $this->argument('client');
        $client = $this->find($model, $identifier);

        if ($client === null && $this->option('create')) {
            if ($model !== ServiceClientModel::class) {
                $this->error("Create the client in {$model} yourself; --create only knows the package model.");

                return self::FAILURE;
            }

            $client = $model::query()->create([
                'name' => $identifier,
                'slug' => Str::slug($identifier),
                'description' => $this->option('description'),
                'is_active' => true,
            ]);

            $this->info("Created service client {$client->displayName()}.");
        }

        if ($client === null) {
            $this->error("No service client '{$identifier}'. Add --create to create it.");

            return self::FAILURE;
        }

        if ($this->option('deactivate')) {
            $client->forceFill(['is_active' => false])->save();
            $this->info("Deactivated {$client->displayName()} — its tokens stop working now.");

            return self::SUCCESS;
        }

        if ($this->option('activate')) {
            $client->forceFill(['is_active' => true])->save();
            $this->info("Activated {$client->displayName()}.");

            return self::SUCCESS;
        }

        if ($this->option('revoke')) {
            $count = $client->tokens()->delete();
            $this->info("Revoked {$count} token(s) of {$client->displayName()}.");

            return self::SUCCESS;
        }

        $abilities = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('abilities')))));

        if ($abilities === []) {
            $abilities = $catalogue->readOnly();
        }

        foreach ($abilities as $ability) {
            if (! $catalogue->exists($ability)) {
                $this->error("Unknown ability '{$ability}'.");

                return self::FAILURE;
            }

            if (! $catalogue->allowedForServiceClient($ability)) {
                $this->error("{$ability} changes data and is not in mcp-kit.catalogue.service_client_writes — service clients may not hold it.");

                return self::FAILURE;
            }
        }

        $token = $client->createToken('service', $abilities);

        $audit->recordToken('minted', $token->accessToken, null, ['service_client' => $client->displayName()]);

        $this->info("Token created for {$client->displayName()}:");
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->components->warn('Copy it now — it is not shown again.');
        $this->line('Abilities: '.implode(', ', $abilities));

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function find(string $model, string $identifier): (Model&ServiceClient)|null
    {
        $query = $model::query();

        $client = $query->where('slug', $identifier)->first()
            ?? $model::query()->where('name', $identifier)->first()
            ?? (is_numeric($identifier) ? $model::query()->find((int) $identifier) : null);

        return $client instanceof ServiceClient ? $client : null;
    }
}
