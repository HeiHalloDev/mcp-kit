<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = $this->table();

        if (! config('mcp-kit.playbooks.enabled', true) || Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();

            // A string, not a foreign key: the users table's key is a bigint
            // in most apps and a uuid in some, and the kit never joins on it.
            $blueprint->string('user_id')->index();

            $blueprint->string('name');
            $blueprint->string('title');
            $blueprint->string('description');
            $blueprint->text('body');

            // Named arguments the person fills in when they run it.
            $blueprint->jsonb('arguments')->nullable();

            // Which servers it belongs to, and which abilities it needs to be
            // runnable. Empty means every server / no requirement.
            $blueprint->jsonb('servers')->nullable();
            $blueprint->jsonb('abilities')->nullable();

            $blueprint->boolean('shared')->default(false)->index();

            $blueprint->unsignedInteger('uses')->default(0);
            $blueprint->timestamp('last_used_at')->nullable();
            $blueprint->timestamps();

            $blueprint->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('mcp-kit.playbooks.table', 'mcp_playbooks');
    }
};
