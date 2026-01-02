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
        Schema::create('filter_registries', function (Blueprint $table) {
            $table->string('id')->primary(); // The filter ID like 'company_name'
            $table->string('label');
            $table->string('group_name');
            $table->json('applies_to'); // ['company', 'contact']
            $table->string('type'); // keyword, text, etc.
            $table->string('input_type'); // text, multi_select, etc.
            $table->string('data_source'); // elasticsearch, direct
            $table->json('fields'); // ['company' => [...], 'contact' => [...]]
            $table->json('search_config')->nullable();
            $table->json('filtering_config')->nullable();
            $table->json('aggregation_config')->nullable();
            $table->json('preloaded_values')->nullable();
            $table->json('range_config')->nullable();
            $table->json('additional_settings')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_registries');
    }
};
