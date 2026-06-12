<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nominations', function (Blueprint $table) {
            foreach ([
                'logo_url' => fn () => $table->string('logo_url')->nullable(),
                'banner_url' => fn () => $table->string('banner_url')->nullable(),
                'website_url' => fn () => $table->string('website_url')->nullable(),
                'discord_invite' => fn () => $table->string('discord_invite')->nullable(),
                'is_verified' => fn () => $table->boolean('is_verified')->default(false),
            ] as $column => $definition) {
                if (!Schema::hasColumn('nominations', $column)) {
                    $definition();
                }
            }
        });

        if (!Schema::hasTable('award_jury_messages')) {
            Schema::create('award_jury_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('award_edition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('message');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('award_jury_messages');
    }
};
