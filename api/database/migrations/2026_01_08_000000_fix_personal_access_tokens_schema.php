<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Fix for ULID compatibility
        // The table might have been created with integer ID, preventing ULID (string) user IDs from saving tokens.

        $table = 'personal_access_tokens';

        if (Schema::hasTable($table)) {
            // Use raw SQL to ensure it runs even if Doctrine DBAL is missing/limited
            // Fix tokenable_id to be string (36 chars for UUID/ULID)
            try {
                DB::statement("ALTER TABLE {$table} MODIFY tokenable_id VARCHAR(64)");
                DB::statement("ALTER TABLE {$table} MODIFY tokenable_type VARCHAR(255)");
            } catch (\Exception $e) {
                // If it fails, it might be SQLite or something else, but for MySQL/MariaDB this is standard.
                // Log warning but don't crash
                \Illuminate\Support\Facades\Log::warning("Failed to alter personal_access_tokens: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // No down migration generally needed as we want to keep it compatible
    }
};
