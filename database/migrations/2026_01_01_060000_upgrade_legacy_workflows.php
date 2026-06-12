<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jury_scores', function (Blueprint $table) {
            foreach (['originality_score', 'activity_score', 'design_score', 'community_score', 'professionalism_score'] as $column) {
                if (!Schema::hasColumn('jury_scores', $column)) {
                    $table->unsignedTinyInteger($column)->nullable()->after('score');
                }
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            $columns = [
                'type' => fn () => $table->string('type', 40)->default('website')->index()->after('priority'),
                'required_role' => fn () => $table->string('required_role', 40)->nullable()->index()->after('type'),
                'is_public' => fn () => $table->boolean('is_public')->default(false)->after('required_role'),
                'progress' => fn () => $table->unsignedTinyInteger('progress')->default(0)->after('is_public'),
                'labels' => fn () => $table->string('labels')->nullable()->after('progress'),
                'image_url' => fn () => $table->string('image_url')->nullable()->after('labels'),
                'claimed_by' => fn () => $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete()->after('image_url'),
                'completed_by' => fn () => $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete()->after('claimed_by'),
                'completed_at' => fn () => $table->timestamp('completed_at')->nullable()->after('deadline'),
                'archived_at' => fn () => $table->timestamp('archived_at')->nullable()->after('completed_at'),
            ];

            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn('tasks', $column)) {
                    $definition();
                }
            }
        });

        if (!Schema::hasColumn('task_comments', 'is_internal')) {
            Schema::table('task_comments', fn (Blueprint $table) => $table->boolean('is_internal')->default(true)->after('body'));
        }

        if (!Schema::hasTable('task_logs')) {
            Schema::create('task_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 80);
                $table->string('old_value')->nullable();
                $table->string('new_value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('task_checklist_items')) {
            Schema::create('task_checklist_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->boolean('is_completed')->default(false);
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Additive production upgrade; intentionally not destructive.
    }
};
