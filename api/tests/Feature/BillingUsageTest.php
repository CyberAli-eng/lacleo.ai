<?php

use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

it('returns usage payload for authenticated user', function () {
    /** @var User $user */
    $user = User::factory()->create();

    /** @var \Tests\TestCase $this */
    $this->actingAs($user);

    $response = $this->getJson('/api/v1/billing/usage');

    $response->assertOk();
    $response->assertJson(fn (AssertableJson $json) => $json
        ->hasAll(['balance', 'plan_total', 'used', 'period_start', 'period_end', 'breakdown', 'free_grants_total', 'stripe_enabled'])
        ->has('breakdown', fn (AssertableJson $b) => $b
            ->hasAll(['reveal_email', 'reveal_phone', 'export_email', 'export_phone', 'adjustments'])
            ->etc()
        )
    );
});
