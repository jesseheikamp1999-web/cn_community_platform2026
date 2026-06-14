<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('name');
            $table->unsignedSmallInteger('retention_days')->nullable()->after('last_message_at');
            $table->timestamp('archived_at')->nullable()->index()->after('retention_days');
        });

        Schema::table('chat_participants', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('is_muted');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')->nullable()->after('sender_id')
                ->constrained('chat_messages')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->after('reply_to_id')
                ->constrained('tasks')->nullOnDelete();
            $table->boolean('is_announcement')->default(false)->after('body');
            $table->boolean('requires_ack')->default(false)->after('is_announcement');
            $table->timestamp('pinned_at')->nullable()->index()->after('requires_ack');
            $table->foreignId('pinned_by')->nullable()->after('pinned_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 20);
            $table->timestamps();
            $table->unique(['message_id', 'user_id', 'emoji']);
            $table->index(['message_id', 'emoji']);
        });

        Schema::create('chat_message_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->unique(['message_id', 'user_id']);
        });

        DB::table('chat_conversations')
            ->whereNotNull('created_by')
            ->select(['id', 'created_by'])
            ->orderBy('id')
            ->each(function (object $conversation): void {
                DB::table('chat_participants')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $conversation->created_by)
                    ->update(['is_admin' => true]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_acknowledgements');
        Schema::dropIfExists('chat_message_reactions');

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_by');
            $table->dropConstrainedForeignId('task_id');
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn(['is_announcement', 'requires_ack', 'pinned_at']);
        });

        Schema::table('chat_participants', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'retention_days', 'archived_at']);
        });
    }
};
