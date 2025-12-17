<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('emits structured request log with keys', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->get('/api/v1/search/companies');
    $log = @file_get_contents(storage_path('logs/structured.log')) ?: '';
    expect($log)->not()->toBe('');
    expect($log)->toContain('request_id');
    expect($log)->toContain('route');
    expect($log)->toContain('method');
    expect($log)->toContain('status');
});

it('redacts pii in request logs', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $payload = ['email' => 'john.doe@example.com', 'phone' => '+1 415 555 1212'];
    $this->post('/api/v1/ai/generate-filters', $payload);
    $log = @file_get_contents(storage_path('logs/structured.log')) ?: '';
    expect($log)->toContain('[REDACTED]');
    expect($log)->not()->toContain('john.doe@example.com');
    expect($log)->not()->toContain('415 555 1212');
});

it('writes audit log for grant credits', function () {
    // ensure admin middleware allows this user
    $email = 'admin.'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)).'@example.com';
    $admin = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    putenv('ADMIN_EMAILS='.$email);
    $_ENV['ADMIN_EMAILS'] = $email;
    $_SERVER['ADMIN_EMAILS'] = $email;
    Sanctum::actingAs($admin);
    $u2 = User::factory()->create();
    $resp = $this->postJson('/api/v1/billing/grant-credits', [
        'user_id' => $u2->id,
        'credits' => 5,
    ]);
    $resp->assertOk();
    $audit = @file_get_contents(storage_path('logs/audit.log')) ?: '';
    expect($audit)->toContain('grant_credits');
    expect($audit)->toContain('request_id');
});
