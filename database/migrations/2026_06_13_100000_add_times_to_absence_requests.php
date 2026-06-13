<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('ends_on')->index();
            $table->dateTime('ends_at')->nullable()->after('starts_at')->index();
        });

        DB::table('absence_requests')
            ->select(['id', 'starts_on', 'ends_on'])
            ->orderBy('id')
            ->each(function (object $absence): void {
                DB::table('absence_requests')->where('id', $absence->id)->update([
                    'starts_at' => $absence->starts_on.' 00:00:00',
                    'ends_at' => $absence->ends_on.' 23:59:59',
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
