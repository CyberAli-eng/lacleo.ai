<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

it('preview-export returns consistent counts and balances', function () {
    $user = User::factory()->create();
    Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 500,
        'credit_reserved' => 0,
    ]);

    $this->actingAs($user);

    $resp = $this->postJson('/api/v1/billing/preview-export', [
        'type' => 'contacts',
        'ids' => ['c1', 'c2', 'c3'],
        'simulate' => [
            'contacts_included' => 3,
            'email_count' => 2,
            'phone_count' => 1,
        ],
    ]);

    $resp->assertOk();
    $resp->assertJson([
        'contacts_included' => 3,
        'email_count' => 2,
        'phone_count' => 1,
        'credits_required' => 2 * 1 + 1 * 4,
    ]);
});
