<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\AiQueryTranslatorService;
use App\Services\FilterRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiSearchController extends Controller
{
    public function __construct(
        protected AiQueryTranslatorService $translator
    ) {
    }

    /**
     * Translate natural language query to filters.
     */
    public function translate(Request $request)
    {
        try {
            $request->headers->set('Accept', 'application/json');
            
            // 1. Get the query from the user
            $incomingQuery = (string) ($request->input('query') ?? $request->input('prompt') ?? '');

            // 2. Prepare the "Dictionary" from your FilterRegistry
            // This tells the AI exactly what names to use (e.g., company_city instead of 'city')
            $registryFilters = FilterRegistry::getFilters();
            $filterGuidelines = collect($registryFilters)->map(function($f) {
                $applies = isset($f['applies_to']) && is_array($f['applies_to']) ? implode(',', $f['applies_to']) : 'unknown';
                return "- ID: {$f['id']} | Label: {$f['label']} | Applies To: {$applies}";
            })->implode("\n");

            // 3. Build the Instructions
            $instruction = "You are a lead generation assistant. Convert the user's query into Canonical DSL JSON.
            AVAILABLE FILTER IDS (from registry):
            {$filterGuidelines}

            Canonical DSL shape:
            {
              \"entity\": \"contacts\" | \"companies\",
              \"filters\": {
                \"contact\": { FILTER_ID: { include: [values], exclude?: [values], range?: { min?: number, max?: number } } },
                \"company\": { FILTER_ID: { include: [values], exclude?: [values], range?: { min?: number, max?: number } } }
              },
              \"summary\": \"short explanation\",
              \"semantic_query\": null | \"optional vector sentence\",
              \"custom\": []
            }

            Routing rules:
            - job_title and other person attributes ALWAYS go under filters.contact.
            - company_name, company_domain, industry, technologies, employee_count, annual_revenue, founded_year go under filters.company.
            - NEVER put job_title under filters.company.
            - When multiple job titles are present, keep the longest/specific ones (e.g., 'AI Engineer' over 'Engineer').
            - If job title detected, entity = contacts; else if only company metrics detected, entity = companies; else default entity = contacts.

            Output ONLY valid JSON in the Canonical DSL shape.";

            // 4. Mimic your original structure to avoid 500 errors
            $messages = [
                ['role' => 'user', 'content' => $instruction . "\n\nUser Query: " . $incomingQuery]
            ];

            // 5. Call the service (Your original method call)
            $result = $this->translator->translate(
                $messages, 
                $request->input('context') ?? []
            );

            // 6. Ensure Canonical DSL buckets exist
            if (!isset($result['filters']) || !is_array($result['filters'])) {
                $result['filters'] = ['contact' => [], 'company' => []];
            }
            if (!isset($result['filters']['contact']) || !is_array($result['filters']['contact'])) {
                $result['filters']['contact'] = [];
            }
            if (!isset($result['filters']['company']) || !is_array($result['filters']['company'])) {
                $result['filters']['company'] = [];
            }
            if (!isset($result['entity'])) {
                $result['entity'] = 'contacts';
            }

            return response()->json($result, 200);

        } catch (\Throwable $e) {
            // Log the error so you can see exactly what went wrong in storage/logs/laravel.log
            Log::error("AI Search Error: " . $e->getMessage());

            return response()->json([
                'status' => 'failed',
                'entity' => 'contacts',
                'filters' => ['contact' => [], 'company' => []],
                'summary' => 'The AI is having trouble. Please try a simpler search.',
                'error_code' => 'AI_INTERNAL_ERROR'
            ], 200); // We return 200 so the frontend doesn't crash
        }
    }
}
