<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Console\Commands;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\TokenPolicy;
use HeiHallo\McpKit\Tokens\TokenMinter;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * Tokens that deserve a look: stale, expired, never-expiring, holding a
 * legacy alias, orphaned (owner gone or blocked), or refused on every call
 * because the owner lost the permission.
 */
class AuditTokensCommand extends Command
{
    protected $signature = 'mcp:audit-tokens
                            {--days=90 : A token unused for this long is stale}
                            {--revoke-orphaned : Revoke tokens whose owner is gone, blocked or no longer allowed}';

    protected $description = 'List MCP tokens that are stale, expired, orphaned or otherwise worth a look';

    public function handle(
        TokenPolicy $policy,
        PrincipalResolver $principals,
        AbilityCatalogue $catalogue,
        PermissionChecker $permissions,
        TokenMinter $minter,
    ): int {
        $model = Sanctum::$personalAccessTokenModel;
        $days = (int) $this->option('days');
        $findings = [];
        $orphaned = [];

        foreach ($model::query()->with('tokenable')->cursor() as $token) {
            if (! $token instanceof PersonalAccessToken) {
                continue;
            }

            $isKit = $policy->isKitToken((string) $token->name) || $this->holdsCatalogueAbility($token, $catalogue);

            if (! $isKit) {
                continue;
            }

            $owner = $token->tokenable;
            $principal = $owner ? $principals->resolve($owner) : null;
            $problems = [];

            if ($owner === null) {
                $problems[] = 'owner gone';
                $orphaned[] = $token;
            } elseif ($principal === null) {
                $problems[] = 'owner is neither a person nor a service client';
                $orphaned[] = $token;
            } elseif ($principal->blocked) {
                $problems[] = $principal->isService() ? 'client inactive' : 'owner blocked';
                $orphaned[] = $token;
            } else {
                foreach ((array) $token->abilities as $ability) {
                    if (! is_string($ability)) {
                        continue;
                    }

                    if ($catalogue->isLegacy($ability)) {
                        $problems[] = "legacy alias {$ability} → {$catalogue->canonical($ability)}";
                    }

                    $required = $catalogue->requiredPermissions($ability);

                    if ($principal->isPerson() && $required !== [] && ! $permissions->hasAnyPermission($owner, $required)) {
                        $problems[] = "{$ability} refused: owner lacks ".implode('|', $required);
                        $orphaned[] = $token;
                    }
                }
            }

            if ($token->expires_at !== null && $token->expires_at->isPast()) {
                $problems[] = 'expired '.$token->expires_at->toDateString();
            } elseif ($token->expires_at === null) {
                $problems[] = 'never expires';
            }

            if (($token->last_used_at ?? $token->created_at)?->lt(now()->subDays($days))) {
                $problems[] = 'unused for '.$days.'+ days';
            }

            if ($problems !== []) {
                $findings[] = [
                    $token->id,
                    $token->name,
                    $principal?->name ?? '—',
                    implode('; ', array_unique($problems)),
                ];
            }
        }

        if ($findings === []) {
            $this->info('Nothing to report: every MCP token has an active owner, a sensible expiry and recent use.');

            return self::SUCCESS;
        }

        $this->table(['Id', 'Token', 'Owner', 'Findings'], $findings);

        if ($this->option('revoke-orphaned')) {
            $revoked = 0;

            foreach (array_unique($orphaned, SORT_REGULAR) as $token) {
                $minter->revoke($token);
                $revoked++;
            }

            $this->info("Revoked {$revoked} orphaned token(s).");
        } elseif ($orphaned !== []) {
            $this->line('Add --revoke-orphaned to revoke the '.count(array_unique($orphaned, SORT_REGULAR)).' token(s) whose owner is gone, blocked or no longer allowed.');
        }

        return self::SUCCESS;
    }

    protected function holdsCatalogueAbility(PersonalAccessToken $token, AbilityCatalogue $catalogue): bool
    {
        foreach ((array) $token->abilities as $ability) {
            if (is_string($ability) && $ability !== '*' && $catalogue->exists($ability)) {
                return true;
            }
        }

        return false;
    }
}
