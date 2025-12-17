<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->updateOrInsert(['id' => 'free'], [
            'id' => 'free',
            'name' => 'Free',
            'monthly_credits' => 100,
            'price' => 0,
            'stripe_price_id' => null,
            'features' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plans')->updateOrInsert(['id' => 'starter'], [
            'id' => 'starter',
            'name' => 'Starter',
            'monthly_credits' => 500,
            'price' => 0,
            'stripe_price_id' => env('STRIPE_PRICE_STARTER'),
            'features' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plans')->updateOrInsert(['id' => 'premium'], [
            'id' => 'premium',
            'name' => 'Premium',
            'monthly_credits' => 2000,
            'price' => 0,
            'stripe_price_id' => env('STRIPE_PRICE_PREMIUM'),
            'features' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plans')->updateOrInsert(['id' => 'enterprise'], [
            'id' => 'enterprise',
            'name' => 'Enterprise',
            'monthly_credits' => 0,
            'price' => 0,
            'stripe_price_id' => env('STRIPE_PRICE_ENTERPRISE'),
            'features' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('plans')->whereIn('id', ['free', 'starter', 'premium', 'enterprise'])->delete();
    }
};
