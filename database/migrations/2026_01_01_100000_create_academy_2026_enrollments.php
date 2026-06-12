<?php

use App\Services\Academy2026Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('academy_enrollments')) {
            Schema::create('academy_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 30)->default('active');
                $table->timestamp('enrolled_at');
                $table->timestamps();
                $table->unique(['learning_path_id', 'user_id']);
            });
        }

        app(Academy2026Service::class)->sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_enrollments');
    }
};
