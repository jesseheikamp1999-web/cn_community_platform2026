<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_profiles', 'public_status')) {
                $table->string('public_status')->default('active')->after('status');
            }
            if (!Schema::hasColumn('staff_profiles', 'specialties')) {
                $table->json('specialties')->nullable()->after('bio');
            }
            if (!Schema::hasColumn('staff_profiles', 'discord_url')) {
                $table->string('discord_url')->nullable()->after('specialties');
            }
            if (!Schema::hasColumn('staff_profiles', 'is_team_member_of_month')) {
                $table->boolean('is_team_member_of_month')->default(false)->after('discord_url');
            }
        });
    }
};
