<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

it('blocks export with 402 and detailed payload when credits insufficient', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 3, // insufficient for 1 phone (4) or combo
        'credit_reserved' => 0,
    ]);
    $this->actingAs($user);

    $payload = [
        'type' => 'contacts',
        'ids' => ['c1', 'c2'],
        'simulate' => [
            'contacts_included' => 2,
            'email_count' => 1,
            'phone_count' => 1,
        ],
    ];

    $response = $this->postJson('/api/v1/billing/export', $payload, ['request_id' => strtolower(Str::ulid())]);
    $response->assertStatus(402);
    $response->assertJsonStructure(['error', 'email_count', 'phone_count', 'credits_needed', 'balance']);
});

it('allows export and deducts credits when sufficient', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 20,
        'credit_reserved' => 0,
    ]);
    $this->actingAs($user);

    $payload = [
        'type' => 'contacts',
        'ids' => ['c1', 'c2'],
        'simulate' => [
            'contacts_included' => 2,
            'email_count' => 2,
            'phone_count' => 3,
        ],
    ];

    $response = $this->postJson('/api/v1/billing/export', $payload, ['request_id' => strtolower(Str::ulid())]);
    $response->assertOk();
    $response->assertJsonStructure(['url', 'credits_deducted', 'remaining_credits', 'request_id']);
    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(20 - (2 * 1 + 3 * 4));
});

it('preview responds with counts and balance', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 50,
        'credit_reserved' => 0,
    ]);
    $this->actingAs($user);

    $response = $this->postJson('/api/v1/billing/preview-export', [
        'type' => 'contacts',
        'ids' => ['x1', 'x2'],
    ]);
    $response->assertOk();
    $response->assertJsonStructure(['email_count', 'phone_count', 'credits_required', 'balance', 'remaining_after']);
});
