<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

it('returns preview counts and remaining balances', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 1000,
        'credit_reserved' => 0,
    ]);
    $this->actingAs($user);
    // Workspace-user relation not required for preview/export in current architecture
    $response = $this->postJson('/api/v1/billing/preview-export', [
        'type' => 'contacts',
        'ids' => ['c1', 'c2'],
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'email_count', 'phone_count', 'credits_required', 'remaining_before', 'remaining_after', 'contacts_included', 'companies_included',
    ]);
});

it('deducts credits on export and returns cached response for same request_id', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 100,
        'credit_reserved' => 0,
    ]);
    $this->actingAs($user);
    $requestId = strtolower(Str::ulid());

    $payload = [
        'type' => 'contacts',
        'ids' => ['c1'],
        'simulate' => [
            'contacts_included' => 1,
            'email_count' => 10,
            'phone_count' => 5,
        ],
    ];

    $r1 = $this->postJson('/api/v1/billing/export', $payload, ['request_id' => $requestId]);
    $r1->assertOk();
    $r1->assertJsonStructure(['url', 'credits_deducted', 'request_id']);

    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(100 - (10 * 1 + 5 * 4));

    $r2 = $this->postJson('/api/v1/billing/export', $payload, ['request_id' => $requestId]);
    $r2->assertOk();
    $r2->assertJsonStructure(['url', 'credits_deducted', 'request_id']);
    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(100 - (10 * 1 + 5 * 4));
});

it('returns 422 if too many contacts', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 100,
        'credit_reserved' => 0,
    ]);

    $this->actingAs($user);

    $ids = array_fill(0, 50001, 'id');
    $response = $this->postJson('/api/v1/billing/export', [
        'type' => 'contacts', 'ids' => $ids, 'simulate' => ['contacts_included' => 50001],
    ]);
    $response->assertStatus(422);
});

it('returns 402 if insufficient credits', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 5,
        'credit_reserved' => 0,
    ]);
    $this->actingAs($user);
    $requestId = strtolower(Str::ulid());

    $payload = [
        'type' => 'contacts',
        'ids' => ['c1'],
        'simulate' => [
            'contacts_included' => 1,
            'email_count' => 3,
            'phone_count' => 1,
        ],
    ];

    $response = $this->postJson('/api/v1/billing/export', $payload, ['request_id' => $requestId]);
    $response->assertStatus(402);
});
