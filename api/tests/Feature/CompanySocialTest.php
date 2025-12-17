<?php

use App\Models\User;
use App\Services\RecordNormalizer;
use App\Services\SocialResolverService;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('returns cached=false then cached=true on repeated social fetch, refresh resets cache', function () {
    $domain = 'example.com';

    $resp1 = $this->getJson('/api/v1/company/social?domain='.$domain);
    $resp1->assertOk();
    expect((bool) $resp1->json('cached'))->toBeFalse();
    $checked1 = (string) $resp1->json('checked_at');
    expect($checked1)->not()->toBe('');
    $resp1->assertHeader('X-Cache-Hit', 'false');

    $resp2 = $this->getJson('/api/v1/company/social?domain='.$domain);
    $resp2->assertOk();
    expect((bool) $resp2->json('cached'))->toBeTrue();
    $resp2->assertHeader('X-Cache-Hit', 'true');

    $email = 'admin.'.Str::lower(Str::random(6)).'@example.com';
    $admin = User::factory()->create(['email' => $email]);
    putenv('ADMIN_EMAILS='.$email);
    $_ENV['ADMIN_EMAILS'] = $email;
    $_SERVER['ADMIN_EMAILS'] = $email;
    Sanctum::actingAs($admin);

    $resp3 = $this->postJson('/api/v1/company/x1/social/refresh?domain='.$domain);
    $resp3->assertOk();
    expect((bool) $resp3->json('cached'))->toBeFalse();
    $resp3->assertHeader('X-Cache-Hit', 'false');
    $checked3 = (string) $resp3->json('checked_at');
    expect($checked3)->not()->toBe('');
});

it('excludes low-confidence candidates from social results', function () {
    $service = new SocialResolverService;
    $company = RecordNormalizer::normalizeCompany(['linkedin_url' => 'https://linkedin.com/fo']);
    $result = $service->resolveForCompany($company);
    $items = collect($result['social'] ?? []);
    $hasLinkedinLow = $items->contains(function ($i) {
        return ($i['network'] ?? '') === 'linkedin' && (($i['confidence'] ?? 0) < 0.25);
    });
    expect($hasLinkedinLow)->toBeFalse();
});
