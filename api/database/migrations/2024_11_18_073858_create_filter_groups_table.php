<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('filter_groups')) {
            Schema::create('filter_groups', function (Blueprint $table) {
                $table->id();
                // Unique group identifier like role, industry
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->unsignedInteger('sort_order');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_groups');
    }
};
