<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in with a URL (oauth.mode = local). Empty and unused until an app
 * turns oauth on; created regardless, so turning it on is a config change
 * and never a deploy with a migration in it.
 *
 * Nothing secret is stored readable: codes and refresh tokens are kept as
 * hashes. The access token is an ordinary Sanctum token, so it lives where
 * every other kit token lives.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcp_oauth_clients')) {
            Schema::create('mcp_oauth_clients', function (Blueprint $table): void {
                $table->id();
                // What the client sends as client_id. Public, not a secret:
                // every client here is a public client proving itself with PKCE.
                $table->string('client_id', 64)->unique();
                $table->string('name');
                $table->jsonb('redirect_uris');
                $table->string('registered_ip', 45)->nullable();
                $table->timestamp('last_used_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mcp_oauth_codes')) {
            Schema::create('mcp_oauth_codes', function (Blueprint $table): void {
                $table->id();
                $table->string('code_hash', 64)->unique();
                $table->foreignId('client_id')->constrained('mcp_oauth_clients')->cascadeOnDelete();
                $table->string('user_id');
                $table->jsonb('abilities');
                $table->text('redirect_uri');
                $table->string('code_challenge', 128);
                $table->string('resource')->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mcp_oauth_grants')) {
            Schema::create('mcp_oauth_grants', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_id')->constrained('mcp_oauth_clients')->cascadeOnDelete();
                // The person, never the token: tokens rotate every hour, the
                // grant is what the person said yes to.
                $table->string('user_id')->index();
                $table->jsonb('abilities');
                $table->string('resource')->nullable();
                $table->unsignedBigInteger('access_token_id')->nullable()->index();
                $table->string('refresh_hash', 64)->nullable()->unique();
                // The refresh token just replaced, honoured for a few seconds
                // so a client that retries or refreshes twice in parallel is
                // not logged out. The pair it gets back is kept encrypted for
                // that window only.
                $table->string('previous_refresh_hash', 64)->nullable()->index();
                $table->text('grace_payload')->nullable();
                $table->timestamp('rotated_at')->nullable();
                $table->timestamp('refresh_expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable()->index();
                $table->string('revoked_reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_grants');
        Schema::dropIfExists('mcp_oauth_codes');
        Schema::dropIfExists('mcp_oauth_clients');
    }
};
