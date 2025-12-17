<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSearchTranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Mock config to ensure API key "exists" for logic, though Http mock intercepts it
        config(['services.openai.api_key' => 'test-key']);
    }

    public function test_it_translates_natural_language_to_filters_using_openai()
    {
        // Mock expected OpenAI response
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'entity' => 'contacts',
                                'filters' => [
                                    'title' => ['VP'],
                                    'location' => ['city' => ['London']],
                                    'revenue' => ['gte' => 10000000]
                                ]
                            ])
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/ai/translate-query', [
            'query' => 'VP of Sales in London with >10M revenue'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'entity' => 'contacts',
                'filters' => [
                    'title' => ['VP'],
                    'location' => ['city' => ['London']],
                    'revenue' => ['gte' => 10000000]
                ]
            ]);

        // Assert that the correct prompt was sent (basic check)
        Http::assertSent(function ($request) {
            return $request->url() == 'https://api.openai.com/v1/chat/completions' &&
                $request['model'] == 'gpt-4o' &&
                str_contains($request['messages'][1]['content'], 'VP of Sales');
        });
    }

    public function test_it_handles_openai_failure_gracefully()
    {
        Http::fake([
            'api.openai.com/*' => Http::response('Server Error', 500),
        ]);

        $response = $this->postJson('/api/v1/ai/translate-query', [
            'query' => 'Find something'
        ]);

        // Expect empty fallback but successful 200 OK response from our API
        // (The service catches exception/failure and returns safe defaults)
        $response->assertStatus(200)
            ->assertJson([
                'entity' => 'contacts',
                'filters' => []
            ]);
    }

    public function test_it_respects_missing_api_key()
    {
        config(['services.openai.api_key' => null]);

        // Should return empty immediately without calling Http
        Http::fake();

        $response = $this->postJson('/api/v1/ai/translate-query', [
            'query' => 'Find something'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'entity' => 'contacts',
                'filters' => []
            ]);

        Http::assertNothingSent();
    }
}
