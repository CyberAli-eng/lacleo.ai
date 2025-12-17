<?php

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('applies numeric sort without .sort suffix', function () {
    $email = 'admin.'.Str::lower(Str::random(6)).'@example.com';
    $admin = User::factory()->create(['email' => $email]);
    putenv('ADMIN_EMAILS='.$email);
    $_ENV['ADMIN_EMAILS'] = $email;
    $_SERVER['ADMIN_EMAILS'] = $email;
    Sanctum::actingAs($admin);

    $resp = $this->getJson('/api/v1/admin/debug/search?type=company&sort[0][field]=employee_count&sort[0][direction]=asc');
    $resp->assertOk();
    $query = (array) $resp->json('query');
    $sort = (array) ($query['sort'] ?? []);
    expect($sort)->not()->toBeEmpty();
    $first = $sort[0];
    $key = array_key_first($first);
    expect($key)->toBe('employee_count');
});

it('applies text sort using .sort suffix', function () {
    $email = 'admin.'.Str::lower(Str::random(6)).'@example.com';
    $admin = User::factory()->create(['email' => $email]);
    putenv('ADMIN_EMAILS='.$email);
    $_ENV['ADMIN_EMAILS'] = $email;
    $_SERVER['ADMIN_EMAILS'] = $email;
    Sanctum::actingAs($admin);

    $resp = $this->getJson('/api/v1/admin/debug/search?type=company&sort[0][field]=company&sort[0][direction]=desc');
    $resp->assertOk();
    $query = (array) $resp->json('query');
    $sort = (array) ($query['sort'] ?? []);
    expect($sort)->not()->toBeEmpty();
    $first = $sort[0];
    $key = array_key_first($first);
    expect($key)->toBe('company.sort');
});
