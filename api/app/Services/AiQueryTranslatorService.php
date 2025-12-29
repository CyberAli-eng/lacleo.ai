<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiQueryTranslatorService
{
    /**
     * Translate a natural language conversation into structured filters using TinyLlama.
     *
     * @param array $messages History of messages [['role' => 'user'|'assistant', 'content' => '...']]
     * @param array $context Metadata like ['lastResultCount' => 0]
     * @return array{entity: string, filters: array, summary: string, semantic_query: string|null, custom: array}
     */
    public function translate(array $messages, array $context = []): array
    {
        // STEP 1B: Hard fallback if no API key or TinyLlama unavailable
        $baseUrl = config('services.ollama.base_url');
        $model = config('services.ollama.chat_model');
        
        if (!$baseUrl || !$model) {
            return ['entity' => 'contacts', 'filters' => [], 'summary' => 'AI service not configured.', 'semantic_query' => null, 'custom' => []];
        }

        // Extract query from messages (last user message)
        $query = '';
        foreach (array_reverse($messages) as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'user' && isset($msg['content'])) {
                $query = $msg['content'];
                break;
            }
        }

        // If no query found in messages, use empty string but still process
        if (empty($query)) {
            $query = '';
        }

        // Preamble for context-awareness (Result Count)
        $contextPreamble = "";
        if (isset($context['lastResultCount'])) {
            $count = (int) $context['lastResultCount'];
            $contextPreamble = "SYSTEM NOTE: The user's PREVIOUS search yielded exactly {$count} results.\n";
            if ($count === 0) {
                $contextPreamble .= "Since the previous result was 0, you SHOULD suggest broadening the filters (removing strict constraints) or explaining why it might be empty.\n";
            }
        }

        // Build dynamic filter menu from registry
        $registryFilters = \App\Services\FilterRegistry::getFilters();
        $availableFilterList = collect($registryFilters)
            ->map(fn($f) => "ID: {$f['id']} | Label: {$f['label']} | Group: {$f['group']}")
            ->implode("\n");

        $systemPrompt = <<<EOT
You are a lead generation assistant. Convert the user's request into a JSON filter object.
You must ALWAYS output strict JSON. Never output text outside JSON.
Do not infer facts. Only translate the user's instructions into filters.

{$contextPreamble}

ONLY use the following Filter IDs:
{$availableFilterList}

Rules:
- If searching for people, put filters under the "contact" key.
- If searching for companies, put filters under the "company" key.
- Values must be arrays under "include". Ranges should use {"min": number, "max": number}.

Entity Detection:
- If job title terms are present (e.g., engineer, manager, cto, cfo, vp, ai engineer, data scientist, people), set "entity" to "contacts".
- Else if company metrics (industry, employee_count, annual_revenue, technologies) are present, set "entity" to "companies".
- Else default to "contacts".

Example Output:
{
  "entity": "contacts",
  "filters": {
    "contact": { "job_title": { "include": ["CEO"] } },
    "company": { "company_city": { "include": ["London"] } }
  },
  "summary": "Searching for CEOs in London",
  "custom": []
}

OUTPUT FORMAT:
Return ONLY valid JSON.
EOT;

        try {
            // Build the messages array for Ollama
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

            // If no messages, add the query as a user message
            if (empty($apiMessages) || count($apiMessages) === 1) {
                $apiMessages[] = ['role' => 'user', 'content' => $query];
            }

            // Ollama API Call (fail fast and respect app timeout)
            $timeout = (int) env('AI_TRANSLATE_TIMEOUT', 10);
            if ($timeout <= 0) {
                $timeout = 10;
            }
            
            $response = Http::timeout($timeout)
                ->connectTimeout(min(3, $timeout))
                ->post("{$baseUrl}/api/chat", [
                    'model' => $model,
                    'messages' => $apiMessages,
                    'stream' => false,
                    'format' => 'json', // Ollama supports this to force JSON
                    'options' => [
                        'temperature' => 0,
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Ollama API request failed: ' . $response->body());
                return $this->getFallbackResponse($query);
            }

            $content = $response->json('message.content');
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Ollama returned invalid JSON: ' . $content);
                // Attempt simplistic extraction if needed
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $data = json_decode($matches[0], true);
                }

                if (!$data) {
                    return $this->getFallbackResponse($query);
                }
            }

            // Ensure basics exist
            $result = [
                'entity' => $data['entity'] ?? 'contacts',
                'filters' => $data['filters'] ?? ['contact' => [], 'company' => []],
                'semantic_query' => $data['semantic_query'] ?? null,
                'summary' => $data['summary'] ?? 'Updated search filters based on your request.',
                'custom' => $data['custom'] ?? [],
            ];

            // STEP 1C: Apply deterministic safety logic
            return $this->applySafetyLogic($result, $query);

        } catch (\Exception $e) { // Catch global Exception
            Log::error('AiQueryTranslatorService Exception: ' . $e->getMessage());
            return $this->getFallbackResponse($query);
        }
    }

    /**
     * Apply safety logic to ensure minimum filter requirements
     */
    private function applySafetyLogic(array $result, string $query): array
    {
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
        return $result;
    }

    /**
     * Get fallback response when AI service fails
     */
    private function getFallbackResponse(string $query): array
    {
        $result = ['entity' => 'contacts', 'filters' => ['contact' => [], 'company' => []], 'summary' => 'Could not process search request.', 'semantic_query' => null, 'custom' => []];
        return $result;
    }
}
