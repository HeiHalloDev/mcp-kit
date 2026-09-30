<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the person unticked on the consent page. A sign-in that follows the
 * person's permissions (oauth.follow_permissions) adds abilities they gain
 * later, and must never add back one they said no to.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['mcp_oauth_codes', 'mcp_oauth_grants'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'declined')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->jsonb('declined')->nullable()->after('abilities');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['mcp_oauth_codes', 'mcp_oauth_grants'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'declined')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('declined');
                });
            }
        }
    }
};
