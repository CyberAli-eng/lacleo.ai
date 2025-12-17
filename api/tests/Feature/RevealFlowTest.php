<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

it('performs reveal email and phone with idempotency and updates balance', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 10,
        'credit_reserved' => 0,
    ]);

    $this->actingAs($user);

    $requestIdEmail = strtolower(Str::ulid());
    $rEmail1 = $this->postJson('/api/v1/reveal/email', [], ['request_id' => $requestIdEmail]);
    $rEmail1->assertOk();
    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(9);

    $rEmail2 = $this->postJson('/api/v1/reveal/email', [], ['request_id' => $requestIdEmail]);
    $rEmail2->assertOk();
    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(9);

    $requestIdPhone = strtolower(Str::ulid());
    $rPhone1 = $this->postJson('/api/v1/reveal/phone', [], ['request_id' => $requestIdPhone]);
    $rPhone1->assertOk();
    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(5);

    $rPhone2 = $this->postJson('/api/v1/reveal/phone', [], ['request_id' => $requestIdPhone]);
    $rPhone2->assertOk();
    $workspace->refresh();
    expect($workspace->credit_balance)->toBe(5);

    $usage = $this->get('/api/v1/billing/usage');
    $usage->assertOk();
});
