<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discord_sync_requests')) {
            return;
        }

        Schema::create('discord_sync_requests', function (Blueprint $table) {
            $table->id();
            $table->string('api_key_hint')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('item_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_sync_requests');
    }
};
