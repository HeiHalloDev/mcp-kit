<?php

declare(strict_types=1);

use HeiHallo\McpKit\Models\ServiceClient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only when the package model is in use: an app with its own
        // service-client model (or none) does not need the table.
        $model = config('mcp-kit.models.service_client');

        if ($model !== ServiceClient::class || Schema::hasTable('mcp_service_clients')) {
            return;
        }

        Schema::create('mcp_service_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_service_clients');
    }
};
