<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    protected string $apiKey;
    protected string $model = 'text-embedding-3-small';

    public function __construct()
    {
        $this->apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');
    }

    /**
     * Generate an embedding vector for a given text.
     *
     * @param string $text
     * @return array
     * @throws Exception
     */
    public function generate(string $text): array
    {
        // OpenAI recommends replacing newlines with spaces for best results
        $text = str_replace("\n", " ", $text);

        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->model,
                'input' => $text,
            ]);

        if ($response->failed()) {
            Log::error('OpenAI Embedding Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception("Failed to generate embedding: " . $response->body());
        }

        return $response->json('data.0.embedding');
    }

    /**
     * Generate description for Company to be embedded.
     */
    public function getCompanyEmbeddingText(\App\Models\Company $company): string
    {
        // Enrich context with description, industry, and keywords
        $parts = [
            "Company: " . $company->company,
            "Industry: " . $company->industry,
            "Description: " . ($company->seoDescription ?? $company->businessDescription ?? ''),
            "Keywords: " . implode(", ", $company->keywords ?? []),
            "Services: " . $company->serviceProduct,
        ];
        return implode(". ", array_filter($parts));
    }

    /**
     * Generate description for Contact to be embedded.
     */
    public function getContactEmbeddingText(\App\Models\Contact $contact): string
    {
        // Enrich context
        $parts = [
            "Person: " . $contact->full_name,
            "Title: " . $contact->title,
            "History: " . $contact->company, // Context of where they work
            "Departments: " . implode(", ", $contact->departments ?? []),
        ];
        return implode(". ", array_filter($parts));
    }
}
