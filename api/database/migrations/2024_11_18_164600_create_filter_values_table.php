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
        if (!Schema::hasTable('filter_values')) {
            Schema::create('filter_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('filter_id');
                $table->string('value_id')->unique();
                $table->string('display_value');
                $table->longText('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['filter_id', 'value_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_values');
    }
};
