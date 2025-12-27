<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old unique constraint on value_id
        Schema::table('filter_values', function (Blueprint $table) {
            $table->dropUnique('filter_values_value_id_unique');
        });

        // Add a composite unique constraint on (filter_id, value_id)
        Schema::table('filter_values', function (Blueprint $table) {
            $table->unique(['filter_id', 'value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('filter_values', function (Blueprint $table) {
            $table->dropUnique('filter_values_filter_id_value_id_unique');
        });

        Schema::table('filter_values', function (Blueprint $table) {
            $table->unique('value_id');
        });
    }
};
