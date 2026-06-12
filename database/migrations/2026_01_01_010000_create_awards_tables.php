<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('award_editions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['cn_awards', 'mini_awards'])->index();
            $table->unsignedSmallInteger('year');
            $table->enum('status', ['draft', 'nominations', 'voting', 'jury', 'finale', 'published', 'archived'])->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('finale_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('award_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_edition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('jury_weight', 5, 2)->default(40);
            $table->decimal('public_weight', 5, 2)->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['award_edition_id', 'slug']);
        });

        Schema::create('award_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_edition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['nomination', 'public_vote', 'jury', 'finale']);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('nominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nominee_name');
            $table->string('nominee_discord_id')->nullable();
            $table->text('motivation');
            $table->enum('status', ['pending', 'approved', 'rejected', 'duplicate', 'finalist', 'winner'])->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('round_id')->constrained('award_rounds')->cascadeOnDelete();
            $table->string('ip_hash', 64)->index();
            $table->string('user_agent_hash', 64);
            $table->decimal('fraud_score', 5, 2)->default(0);
            $table->boolean('is_valid')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'round_id', 'nomination_id']);
        });

        Schema::create('jury_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jury_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->unsignedTinyInteger('originality_score')->nullable();
            $table->unsignedTinyInteger('activity_score')->nullable();
            $table->unsignedTinyInteger('design_score')->nullable();
            $table->unsignedTinyInteger('community_score')->nullable();
            $table->unsignedTinyInteger('professionalism_score')->nullable();
            $table->text('report')->nullable();
            $table->timestamps();
            $table->unique(['nomination_id', 'jury_id']);
        });

        Schema::create('award_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nomination_id')->constrained()->cascadeOnDelete();
            $table->decimal('final_score', 8, 3);
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamp('revealed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['award_winners', 'jury_scores', 'votes', 'nominations', 'award_rounds', 'award_categories', 'award_editions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
