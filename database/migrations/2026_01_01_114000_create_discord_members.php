<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('discord_members')) {
            Schema::create('discord_members', function (Blueprint $table) {
                $table->id();
                $table->string('discord_id')->unique();
                $table->string('username');
                $table->string('display_name');
                $table->string('avatar')->nullable();
                $table->string('platform_role')->default('member')->index();
                $table->json('roles')->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->boolean('is_bot')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('synced_at')->nullable()->index();
                $table->timestamps();
            });
        }

        DB::table('users')
            ->whereNotNull('discord_id')
            ->where('discord_id', '!=', '')
            ->orderBy('id')
            ->each(function ($user) {
                DB::table('discord_members')->updateOrInsert(
                    ['discord_id' => $user->discord_id],
                    [
                        'username' => $user->discord_username ?: $user->name,
                        'display_name' => $user->name,
                        'avatar' => $user->discord_avatar,
                        'platform_role' => $user->role,
                        'roles' => json_encode([]),
                        'is_bot' => false,
                        'is_active' => true,
                        'synced_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_members');
    }
};
