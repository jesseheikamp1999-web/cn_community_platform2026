<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->index();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('reviewed_at')->index();
        });

        DB::table('applications')
            ->whereIn('status', ['accepted', 'rejected'])
            ->update(['archived_at' => DB::raw('COALESCE(reviewed_at, updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });

        Schema::dropIfExists('automation_logs');
    }
};
