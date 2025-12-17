<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

it('deducts 1 credit for email reveal and is idempotent', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 10,
        'credit_reserved' => 0,
    ]);

    $this->actingAs($user);
    $requestId = strtolower(Str::ulid());

    $r1 = $this->postJson('/api/v1/reveal/email', [], ['request_id' => $requestId]);
    $r1->assertOk();

    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(9);

    $r2 = $this->postJson('/api/v1/reveal/email', [], ['request_id' => $requestId]);
    $r2->assertOk();

    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(9);
});

it('deducts 4 credits for phone reveal and is idempotent', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 10,
        'credit_reserved' => 0,
    ]);

    $this->actingAs($user);
    $requestId = strtolower(Str::ulid());

    $r1 = $this->postJson('/api/v1/reveal/phone', [], ['request_id' => $requestId]);
    $r1->assertOk();

    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(6);

    $r2 = $this->postJson('/api/v1/reveal/phone', [], ['request_id' => $requestId]);
    $r2->assertOk();

    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(6);
});
