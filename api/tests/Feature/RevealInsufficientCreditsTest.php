<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

it('returns 402 with details when email reveal balance is short', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 0,
        'credit_reserved' => 0,
    ]);

    $this->actingAs($user);
    $response = $this->postJson('/api/v1/reveal/email', []);
    $response->assertStatus(402);
    $response->assertJsonStructure(['error', 'balance', 'needed', 'short_by']);
});

it('returns 402 with details when phone reveal balance is short', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 3,
        'credit_reserved' => 0,
    ]);

    $this->actingAs($user);
    $response = $this->postJson('/api/v1/reveal/phone', []);
    $response->assertStatus(402);
    $response->assertJsonStructure(['error', 'balance', 'needed', 'short_by']);
});
