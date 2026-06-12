<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->preserveLegacyTable('users', 'legacy_users', 'auth_provider');
        $this->preserveLegacyTable('votes', 'legacy_votes', 'submission_id');
        $this->preserveLegacyTable('jury_scores', 'legacy_jury_scores', 'submission_id');
        $this->preserveLegacyTable('partners', 'legacy_partners', 'partner_type');
    }

    public function down(): void
    {
        // This migration intentionally never overwrites or renames legacy data back.
    }

    private function preserveLegacyTable(string $table, string $legacyTable, string $legacyColumn): void
    {
        if (
            Schema::hasTable($table)
            && Schema::hasColumn($table, $legacyColumn)
            && !Schema::hasTable($legacyTable)
        ) {
            Schema::rename($table, $legacyTable);
        }
    }
};
