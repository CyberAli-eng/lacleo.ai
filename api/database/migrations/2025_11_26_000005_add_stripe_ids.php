<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('plan_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('stripe_subscription_id');
        });
    }
};
