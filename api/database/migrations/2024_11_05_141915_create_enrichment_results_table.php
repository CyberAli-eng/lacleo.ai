<?php

use App\Models\EnrichmentRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrichment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(EnrichmentRequest::class);
            $table->string('reference_id')->nullable();
            $table->longText('raw_response')->nullable();
            $table->timestamps();

            $table->index('enrichment_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrichment_results');
    }
};
