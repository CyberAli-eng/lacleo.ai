<?php

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

function stripeSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signedPayload = $timestamp.'.'.$payload;
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return "t={$timestamp},v1={$signature}";
}

it('increments credits on checkout.session.completed payment webhook', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 0,
        'credit_reserved' => 0,
    ]);

    $plan = Plan::create([
        'id' => (string) strtolower(Str::ulid()),
        'name' => 'Basic Pack',
        'monthly_credits' => 50,
        'price' => 0,
        'stripe_price_id' => 'price_basic',
        'features' => [],
    ]);

    $event = [
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test',
                'mode' => 'payment',
                'customer' => 'cus_test',
                'metadata' => [
                    'workspace_id' => $workspace->id,
                    'credits' => 50,
                ],
            ],
        ],
    ];

    $payload = json_encode($event);
    $sig = stripeSignature($payload, 'whsec_test');

    $response = $this->postJson('/api/v1/billing/webhook/stripe', $event, [
        'Stripe-Signature' => $sig,
    ]);

    $response->assertOk();

    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(50);
});
