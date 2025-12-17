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
        Schema::create('enrichment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_id')->nullable();
            $table->string('status');
            $table->json('request_data');
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('last_processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'transaction_id']);
            $table->index('last_processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrichment_requests');
    }
};
