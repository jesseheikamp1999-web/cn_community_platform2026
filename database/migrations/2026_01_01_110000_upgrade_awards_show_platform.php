<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE nominations MODIFY status ENUM('pending','approved','rejected','duplicate','finalist','winner') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('nominations', function (Blueprint $table) {
            foreach ([
                'evidence_url' => fn () => $table->string('evidence_url')->nullable()->after('motivation'),
                'evidence_text' => fn () => $table->text('evidence_text')->nullable()->after('evidence_url'),
                'logo_url' => fn () => $table->string('logo_url')->nullable()->after('evidence_text'),
                'banner_url' => fn () => $table->string('banner_url')->nullable()->after('logo_url'),
                'website_url' => fn () => $table->string('website_url')->nullable()->after('banner_url'),
                'discord_invite' => fn () => $table->string('discord_invite')->nullable()->after('website_url'),
                'is_verified' => fn () => $table->boolean('is_verified')->default(false)->after('discord_invite'),
                'canonical_nomination_id' => fn () => $table->foreignId('canonical_nomination_id')->nullable()->constrained('nominations')->nullOnDelete()->after('status'),
                'duplicate_count' => fn () => $table->unsignedInteger('duplicate_count')->default(0)->after('canonical_nomination_id'),
                'spam_score' => fn () => $table->decimal('spam_score', 5, 2)->default(0)->after('duplicate_count'),
                'review_note' => fn () => $table->text('review_note')->nullable()->after('reviewed_at'),
                'reputation_score' => fn () => $table->decimal('reputation_score', 8, 2)->default(0)->after('review_note'),
            ] as $column => $definition) {
                if (!Schema::hasColumn('nominations', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('votes', function (Blueprint $table) {
            foreach ([
                'browser_fingerprint' => fn () => $table->string('browser_fingerprint', 64)->nullable()->after('user_agent_hash'),
                'discord_account_age_days' => fn () => $table->unsignedInteger('discord_account_age_days')->nullable()->after('browser_fingerprint'),
                'superseded_at' => fn () => $table->timestamp('superseded_at')->nullable()->after('is_valid'),
            ] as $column => $definition) {
                if (!Schema::hasColumn('votes', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('jury_scores', function (Blueprint $table) {
            foreach ([
                'impact_score' => fn () => $table->unsignedTinyInteger('impact_score')->nullable()->after('score'),
                'innovation_score' => fn () => $table->unsignedTinyInteger('innovation_score')->nullable()->after('professionalism_score'),
                'future_score' => fn () => $table->unsignedTinyInteger('future_score')->nullable()->after('innovation_score'),
                'strengths' => fn () => $table->text('strengths')->nullable()->after('future_score'),
                'improvements' => fn () => $table->text('improvements')->nullable()->after('strengths'),
                'personal_note' => fn () => $table->text('personal_note')->nullable()->after('improvements'),
            ] as $column => $definition) {
                if (!Schema::hasColumn('jury_scores', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('award_winners', function (Blueprint $table) {
            foreach ([
                'community_score' => fn () => $table->decimal('community_score', 8, 3)->default(0)->after('final_score'),
                'jury_score' => fn () => $table->decimal('jury_score', 8, 3)->default(0)->after('community_score'),
                'jury_highlights' => fn () => $table->json('jury_highlights')->nullable()->after('position'),
                'revealed_position_at' => fn () => $table->timestamp('revealed_position_at')->nullable()->after('revealed_at'),
            ] as $column => $definition) {
                if (!Schema::hasColumn('award_winners', $column)) {
                    $definition();
                }
            }
        });

        if (!Schema::hasTable('vote_histories')) {
            Schema::create('vote_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('round_id')->constrained('award_rounds')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('award_category_id')->constrained()->cascadeOnDelete();
                $table->foreignId('from_nomination_id')->nullable()->constrained('nominations')->nullOnDelete();
                $table->foreignId('to_nomination_id')->constrained('nominations')->cascadeOnDelete();
                $table->string('ip_hash', 64);
                $table->string('user_agent_hash', 64);
                $table->string('browser_fingerprint', 64)->nullable();
                $table->decimal('fraud_score', 5, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('award_jury_assignments')) {
            Schema::create('award_jury_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('award_edition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('award_category_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('panel_name')->nullable();
                $table->boolean('is_chair')->default(false);
                $table->timestamps();
                $table->unique(['award_edition_id', 'award_category_id', 'user_id'], 'award_jury_unique');
            });
        }

        if (!Schema::hasTable('nomination_review_logs')) {
            Schema::create('nomination_review_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('nomination_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 40);
                $table->string('old_status', 40)->nullable();
                $table->string('new_status', 40)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

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
        Schema::dropIfExists('nomination_review_logs');
        Schema::dropIfExists('award_jury_messages');
        Schema::dropIfExists('award_jury_assignments');
        Schema::dropIfExists('vote_histories');
    }
};
