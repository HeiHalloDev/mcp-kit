<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages to the people using the tools, and whether each reached them.
 * A message rides on the person's next tool results until their assistant
 * confirms it passed it on: an answer left in whoami was marked heard the
 * moment it was read, and staff told in a meeting they had never heard of
 * tools built for them weeks before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcp_messages')) {
            Schema::create('mcp_messages', function (Blueprint $table): void {
                $table->id();
                $table->text('body');
                $table->json('to_people')->nullable();
                $table->string('to_ability')->nullable();
                $table->boolean('to_everyone')->default(false);
                $table->string('server')->nullable();
                $table->string('sent_by');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mcp_message_receipts')) {
            Schema::create('mcp_message_receipts', function (Blueprint $table): void {
                $table->id();
                $table->string('message_key');
                $table->string('recipient');
                $table->unsignedInteger('shown')->default(0);
                $table->timestamp('last_shown_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
                $table->unique(['message_key', 'recipient']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_message_receipts');
        Schema::dropIfExists('mcp_messages');
    }
};
