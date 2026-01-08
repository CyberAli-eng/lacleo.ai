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
        if (!Schema::hasTable('enrichment_results')) {
            Schema::create('enrichment_results', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('enrichment_request_id');
                $table->string('external_id')->index();
                $table->enum('entity_type', ['contact', 'company']);
                $table->longText('data');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrichment_results');
    }
};
