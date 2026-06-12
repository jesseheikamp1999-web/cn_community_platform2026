<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_bio')) {
                $table->string('profile_bio', 280)->nullable()->after('birth_date');
            }
            if (!Schema::hasColumn('users', 'birthday_visibility')) {
                $table->string('birthday_visibility', 20)->default('staff')->after('profile_bio');
            }
            if (!Schema::hasColumn('users', 'birthday_notifications')) {
                $table->boolean('birthday_notifications')->default(true)->after('birthday_visibility');
            }
        });
    }

    public function down(): void
    {
        // Additive production migration; profile data is intentionally preserved.
    }
};
