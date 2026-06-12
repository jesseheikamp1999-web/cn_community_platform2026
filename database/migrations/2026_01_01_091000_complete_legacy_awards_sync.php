<?php

use App\Services\LegacyAwardsSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LegacyAwardsSyncService::class)->run();
    }

    public function down(): void
    {
        // Gesynchroniseerde productiedata wordt bewust niet verwijderd.
    }
};
