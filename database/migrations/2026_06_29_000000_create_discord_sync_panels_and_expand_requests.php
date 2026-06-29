<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('discord_sync_panels')) {
            Schema::create('discord_sync_panels', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('button_label')->nullable();
                $table->string('button_url')->nullable();
                $table->string('secondary_button_label')->nullable();
                $table->string('secondary_button_url')->nullable();
                $table->unsignedSmallInteger('refresh_after_seconds')->default(300);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('discord_sync_requests')) {
            $missingColumns = collect([
                'channel_key',
                'status_code',
                'ip_address',
                'user_agent',
            ])->reject(fn (string $column) => Schema::hasColumn('discord_sync_requests', $column));

            if ($missingColumns->isNotEmpty()) {
                Schema::table('discord_sync_requests', function (Blueprint $table) use ($missingColumns) {
                    if ($missingColumns->contains('channel_key')) {
                        $table->string('channel_key')->nullable()->after('api_key_hint');
                    }
                    if ($missingColumns->contains('status_code')) {
                        $table->unsignedSmallInteger('status_code')->default(200)->after('success');
                    }
                    if ($missingColumns->contains('ip_address')) {
                        $table->string('ip_address', 45)->nullable()->after('error_message');
                    }
                    if ($missingColumns->contains('user_agent')) {
                        $table->string('user_agent', 255)->nullable()->after('ip_address');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('discord_sync_requests')) {
            $existingColumns = collect([
                'channel_key',
                'status_code',
                'ip_address',
                'user_agent',
            ])->filter(fn (string $column) => Schema::hasColumn('discord_sync_requests', $column));

            if ($existingColumns->isNotEmpty()) {
                Schema::table('discord_sync_requests', function (Blueprint $table) use ($existingColumns) {
                    $table->dropColumn($existingColumns->all());
                });
            }
        }

        Schema::dropIfExists('discord_sync_panels');
    }
};
