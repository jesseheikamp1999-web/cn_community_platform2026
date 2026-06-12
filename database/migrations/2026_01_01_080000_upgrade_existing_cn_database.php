<?php

use App\Services\LegacyDatabaseUpgradeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legacy_users')) {
            app(LegacyDatabaseUpgradeService::class)->run();
        }
    }

    public function down(): void
    {
        // Imported production data is never removed automatically.
    }
};
