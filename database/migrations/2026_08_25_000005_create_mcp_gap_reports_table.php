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

        if (! config('mcp-kit.gaps.enabled', true) || Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();

            // Matched on when someone hits the same gap again, so the second
            // report adds weight instead of a duplicate row.
            $blueprint->string('key')->index();

            $blueprint->string('title');
            $blueprint->text('need');
            $blueprint->text('missing');

            $blueprint->string('server')->nullable();
            $blueprint->string('tool')->nullable();
            $blueprint->boolean('blocking')->default(false);

            $blueprint->string('status')->default('open')->index();

            // One entry per person who ran into it, with what they said.
            $blueprint->jsonb('reporters')->nullable();
            $blueprint->unsignedInteger('reports')->default(1);

            $blueprint->text('resolution')->nullable();
            $blueprint->string('resolved_by')->nullable();
            $blueprint->timestamp('resolved_at')->nullable();

            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('mcp-kit.gaps.table', 'mcp_gap_reports');
    }
};
