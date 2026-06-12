<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'permissions_locked')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('permissions_locked')->default(false)->after('role');
            });
        }

        Schema::create('question_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('learning_paths')->cascadeOnDelete();
            $table->unsignedTinyInteger('module_id')->nullable()->index();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->cascadeOnDelete();
            $table->enum('type', ['multiple_choice', 'true_false', 'scenario']);
            $table->text('question');
            $table->json('options');
            $table->string('correct_answer', 20);
            $table->text('explanation');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->boolean('is_active')->default(true)->index();
            $table->string('question_hash', 64);
            $table->timestamps();
            $table->unique(['course_id', 'question_hash']);
            $table->index(['course_id', 'module_id', 'lesson_id', 'is_active'], 'question_bank_lookup');
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('learning_paths')->cascadeOnDelete();
            $table->unsignedTinyInteger('module_id')->nullable();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->cascadeOnDelete();
            $table->enum('type', ['lesson', 'quiz', 'exam']);
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->unsignedInteger('tab_switches')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('question_bank')->cascadeOnDelete();
            $table->string('selected_answer', 20);
            $table->string('correct_answer', 20);
            $table->boolean('is_correct');
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('nomi_knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('title');
            $table->longText('content');
            $table->enum('visibility', ['public', 'staff'])->default('public');
            $table->string('checksum', 64);
            $table->timestamp('indexed_at');
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['visibility', 'indexed_at']);
        });

        app(\App\Services\Academy2026Service::class)->sync();
        app(\App\Services\NomiKnowledgeService::class)->refresh();
    }

    public function down(): void
    {
        Schema::dropIfExists('nomi_knowledge_items');
        Schema::dropIfExists('quiz_attempt_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('question_bank');
        if (Schema::hasColumn('users', 'permissions_locked')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('permissions_locked'));
        }
    }
};
