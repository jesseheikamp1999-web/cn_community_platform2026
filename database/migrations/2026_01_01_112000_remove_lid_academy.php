<?php

use App\Services\Academy2026Service;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(Academy2026Service::class)->sync();
    }

    public function down(): void
    {
        // Existing Academy progress and certificates are intentionally not recreated or removed.
    }
};
