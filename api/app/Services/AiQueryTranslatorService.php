<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiQueryTranslatorService
{
    /**
     * Translate a natural language query into structured filters using OpenAI.
     *
     * @param string $query
     * @return array{entity: string, filters: array}
     */
    /**
     * Translate a natural language conversation into structured filters using OpenAI.
     *
     * @param array $messages History of messages [['role' => 'user'|'assistant', 'content' => '...']]
     * @param array $context Metadata like ['lastResultCount' => 0]
     * @return array{entity: string, filters: array, summary: string}
     */
    public function translate(array $messages, array $context = []): array
    {
        // Preamble for context-awareness (Result Count)
        $contextPreamble = "";
        if (isset($context['lastResultCount'])) {
            $count = (int) $context['lastResultCount'];
            $contextPreamble = "SYSTEM NOTE: The user's PREVIOUS search yielded exactly {$count} results.\n";
            if ($count === 0) {
                $contextPreamble .= "Since the previous result was 0, you SHOULD suggest broadening the filters (removing strict constraints) or explaining why it might be empty.\n";
            }
        }

        $systemPrompt = <<<EOT
You are an AI that converts natural language into structured B2B search filters.
You must ALWAYS output strict JSON. Never output text outside JSON. 
Do not infer facts. Only translate the user’s instructions into filters.

{$contextPreamble}

YOUR JOB:
1. Analyze the entire CONVERSATION HISTORY to understand the current search context.
2. Detect whether the query is about CONTACTS or COMPANIES.
3. Extract only what the user explicitly describes or implies based on previous context.
4. If the user says "refine" or "remove", modify the previous filters accordingly.
5. Map natural-language terms into normalized filter fields.
6. NEVER guess unknown company names, industries, or locations.
7. Keep output minimal and safe.

NORMALIZED FIELDS YOU ARE ALLOWED TO USE:
{
  "entity": "contacts" | "companies",

  "filters": {
    "job_title": { "include": [string], "exclude": [string] },      
    "departments": { "include": [string], "exclude": [string] },    
    "seniority": { "include": [string], "exclude": [string] },      
    "company_names": { "include": [string], "exclude": [string] },  
    "employee_count": { "min": num, "max": num }, 
    "revenue": { "min": num, "max": num },     
    "locations": { "include": [string], "exclude": [string] },      
    "technologies": { "include": [string], "exclude": [string] },   
    "industries": { "include": [string], "exclude": [string] },     
    "company_keywords": { "include": [string], "exclude": [string] },
    "years_of_experience": { "min": num, "max": num }
  },
  "semantic_query": "Optional: A descriptive sentence for vector search if the user asks for concepts/lookalikes (e.g. 'Sustainable logistics companies' or 'Competitors to Stripe').",
  "summary": "Short explanation of what filters were applied (e.g. 'Searching for SaaS companies in NY with Revenue > 1M')" 
}

INTERPRETATION RULES:
- If the newest message changes the topic entirely, reset the filters.
- If the newest message is a refinement (e.g. "also in Texas", "remove managers"), merge strict logic with previous valid filters.
- **CRITICAL**: Use "company_keywords" for ANY topics, themes, business models, or context matching (e.g. "CRM", "Marketplace", "B2B", "Conferences", "Events").
- **SEMANTIC SEARCH**: If the user asks for "Companies like [Conmpany]" or "Startups in [Niche]", generate a `semantic_query` describing the ideal target.
- If the user asks for something unsupported (e.g. "last 6 months", "attended event"), **IGNORE** the constraint but **EXTRACT** the topic into "company_keywords".
- Map synonyms (short list only):
  HR → Human Resources
  AI Engineer → Machine Learning Engineer
  Software Engineer → Developer, Programmer
  Sales → Business Development
- For Locations, convert to Country/State/City names.
- For Revenue, convert to numbers (1M = 1000000).
- For Size, convert to numbers.

STRICT SAFETY RULES:
- DO NOT hallucinate company names.
- DO NOT add fields the user did not mention.
- DO NOT infer revenue, size, or experience unless explicitly stated.
- **PREFER** extracting keywords over returning empty filters. If a term is significant, put it in `company_keywords`.

OUTPUT FORMAT:
Return ONLY valid JSON:
{
  "entity": "...",
  "filters": { ... },
  "semantic_query": "...",
  "summary": "..."
}
EOT;

        try {
            $apiKey = config('services.openai.api_key');

            if (empty($apiKey)) {
                Log::warning('OpenAI API key not configured. Falling back to empty search.');
                return ['entity' => 'contacts', 'filters' => [], 'summary' => 'Search functionality is currently unavailable.'];
            }

            // Build the messages array for OpenAI
            // Prepend system prompt
            $apiMessages = [['role' => 'system', 'content' => $systemPrompt]];

            // Append conversation history (sanitized)
            foreach ($messages as $msg) {
                if (isset($msg['role'], $msg['content']) && in_array($msg['role'], ['user', 'assistant'])) {
                    $apiMessages[] = [
                        'role' => $msg['role'],
                        'content' => substr($msg['content'], 0, 1000) // Limit length for safety
                    ];
                }
            }

            $response = Http::withToken($apiKey)
                ->timeout(20) // Increased timeout for potentially longer context processing
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o',
                    'messages' => $apiMessages,
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                Log::error('OpenAI API request failed: ' . $response->body());
                return ['entity' => 'contacts', 'filters' => [], 'summary' => 'Failed to process search request.'];
            }

            $content = $response->json('choices.0.message.content');
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('OpenAI returned invalid JSON: ' . $content);
                return ['entity' => 'contacts', 'filters' => [], 'summary' => 'Could not understand the AI response.'];
            }

            // Ensure basics exist
            return [
                'entity' => $data['entity'] ?? 'contacts',
                'filters' => $data['filters'] ?? [],
                'semantic_query' => $data['semantic_query'] ?? null,
                'summary' => $data['summary'] ?? 'Updated search filters based on your request.',
            ];

        } catch (\Exception $e) { // Catch global Exception
            Log::error('AiQueryTranslatorService Exception: ' . $e->getMessage());
            return ['entity' => 'contacts', 'filters' => [], 'summary' => 'An error occurred while processing your request.'];
        }
    }
}
