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

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();

            // The token is what a call arrives with, so it is what an open
            // task is found by.
            $blueprint->string('token_id')->index();
            $blueprint->string('user_id')->index();
            $blueprint->string('name');

            $blueprint->text('purpose');
            $blueprint->string('server')->nullable();

            $blueprint->string('outcome')->default('open')->index();
            $blueprint->text('result')->nullable();

            $blueprint->unsignedInteger('calls')->default(0);

            $blueprint->timestamp('closed_at')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('mcp-kit.learning.table', 'mcp_tasks');
    }
};
