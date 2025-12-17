<?php

use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;

it('AI filter generation returns canonical fields', function () {
    $payload = [
        'mode' => 'generate_filters',
        'context' => 'contact',
        'query' => 'title: Software Engineer located in California at Acme Corp',
    ];

    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/ai/generate-filters', $payload);

    $response->assertOk();
    $filters = $response->json('filters') ?? [];
    expect($filters)->toBeArray()->not()->toBeEmpty();
    $fields = array_map(fn ($f) => $f['field'] ?? null, $filters);
    expect($fields)->toContain('title');
    expect($fields)->toContain('state');
    expect($fields)->toContain('company');
});

it('Billing usage breakdown uses absolute values for spends', function () {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var \Tests\TestCase $this */
    $this->actingAs($user);

    $workspace = Workspace::firstOrCreate(
        ['owner_user_id' => $user->id],
        ['id' => (string) strtolower(Str::ulid()), 'credit_balance' => 100, 'credit_reserved' => 0]
    );

    CreditTransaction::create([
        'id' => (string) strtolower(Str::ulid()),
        'workspace_id' => $workspace->id,
        'amount' => -1,
        'type' => 'spend',
        'meta' => ['category' => 'enrichment'],
    ]);
    CreditTransaction::create([
        'id' => (string) strtolower(Str::ulid()),
        'workspace_id' => $workspace->id,
        'amount' => -10,
        'type' => 'spend',
        'meta' => ['category' => 'export'],
    ]);

    $resp = $this->getJson('/api/v1/billing/usage');
    $resp->assertOk();
    $resp->assertJson(fn (AssertableJson $json) => $json
        ->has('breakdown', fn (AssertableJson $b) => $b
            ->hasAll(['export_email', 'export_phone'])
            ->etc()
        )
        ->etc()
    );
});
