<?php

use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;

it('returns breakdown sums and free grants total', function () {
    $user = User::factory()->create();
    $workspace = Workspace::firstOrCreate(
        ['owner_user_id' => $user->id],
        ['id' => (string) strtolower(Str::ulid()), 'credit_balance' => 100, 'credit_reserved' => 0]
    );
    CreditTransaction::create([
        'id' => (string) strtolower(Str::ulid()),
        'workspace_id' => $workspace->id,
        'amount' => -3,
        'type' => 'spend',
        'meta' => ['category' => 'enrichment'],
    ]);
    CreditTransaction::create([
        'id' => (string) strtolower(Str::ulid()),
        'workspace_id' => $workspace->id,
        'amount' => -7,
        'type' => 'spend',
        'meta' => ['category' => 'export'],
    ]);
    CreditTransaction::create([
        'id' => (string) strtolower(Str::ulid()),
        'workspace_id' => $workspace->id,
        'amount' => 15,
        'type' => 'adjustment',
        'meta' => ['reason' => 'free_grant'],
    ]);

    $this->actingAs($user);
    $resp = $this->getJson('/api/v1/billing/usage');
    $resp->assertOk();
    $resp->assertJson(fn (AssertableJson $json) => $json
        ->hasAll(['balance', 'used', 'breakdown', 'free_grants_total'])
        ->where('free_grants_total', 15)
        ->has('breakdown', fn (AssertableJson $b) => $b
            ->where('reveal_email', 0)
            ->where('reveal_phone', 0)
            ->where('export_email', 0)
            ->where('export_phone', 7)
            ->where('adjustments', 0)
        )
    );
});

it('preview sanitize=true yields zero credits_required', function () {
    $user = User::factory()->create();
    Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 50,
        'credit_reserved' => 0,
    ]);
    $this->actingAs($user);
    $resp = $this->postJson('/api/v1/billing/preview-export', [
        'type' => 'contacts',
        'ids' => ['x1', 'x2'],
        'sanitize' => true,
        'simulate' => [
            'contacts_included' => 2,
            'email_count' => 2,
            'phone_count' => 1,
        ],
    ]);
    $resp->assertOk();
    expect((int) $resp->json('credits_required'))->toBe(0);
});

it('preview with huge count returns 422', function () {
    $user = User::factory()->create();
    Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 0,
        'credit_reserved' => 0,
    ]);
    $this->actingAs($user);
    $resp = $this->postJson('/api/v1/billing/export', [
        'type' => 'contacts',
        'ids' => array_fill(0, 50001, 'id'),
        'simulate' => ['contacts_included' => 50001],
    ]);
    $resp->assertStatus(422);
});
