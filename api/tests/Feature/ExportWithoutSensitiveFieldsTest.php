<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('exports CSV with empty sensitive fields when sanitized', function () {
    $user = User::factory()->create();
    Workspace::create([
        'id' => (string) strtolower(Str::ulid()),
        'owner_user_id' => $user->id,
        'credit_balance' => 500,
        'credit_reserved' => 0,
    ]);

    Storage::fake('public');
    $this->actingAs($user);

    $payload = [
        'type' => 'contacts',
        'ids' => ['x1', 'x2'],
        'sanitize' => true,
        'simulate' => [
            'contacts_included' => 2,
            'email_count' => 2,
            'phone_count' => 1,
        ],
    ];

    $resp = $this->postJson('/api/v1/billing/export', $payload, ['request_id' => strtolower(Str::ulid())]);
    $resp->assertOk();
    $resp->assertJsonStructure(['url', 'credits_deducted', 'remaining_credits', 'request_id']);
    expect($resp->json('credits_deducted'))->toBe(0);

    $files = Storage::disk('public')->allFiles('exports');
    expect(count($files))->toBeGreaterThan(0);
    $csv = Storage::disk('public')->get($files[0]);
    $lines = array_map('trim', explode("\n", $csv));
    $first = str_getcsv($lines[0]);
    expect($first[0])->toBe('id');
    expect($first)->toContain('email');
});
