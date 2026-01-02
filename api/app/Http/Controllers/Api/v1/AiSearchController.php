<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\AiQueryTranslatorService;
use App\Services\FilterRegistry;
use App\Validators\DslValidator;
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

            // Build conversation: only the raw user query; system instructions are injected by the service
            $messages = [
                ['role' => 'user', 'content' => (string) $incomingQuery]
            ];

            // 5. Call the service (Your original method call)
            $result = $this->translator->translate(
                $messages,
                $request->input('context') ?? []
            );

            // Ensure Canonical DSL buckets exist
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

            // 7. GUARDRAIL: Validate DSL structure and filter placement
            $validation = DslValidator::validate($result['filters']);
            $normalized = $validation['normalized'] ?? $result['filters'];
            $entity = DslValidator::detectEntity($normalized);

            // 8. Return canonical shape directly (Frontend supports it and it prevents data loss)
            // The frontend checks for 'contact'/'company' keys and uses the canonical DSL if present.
            return response()->json([
                'entity' => $entity,
                'filters' => $normalized, // Return full canonical DSL
                'summary' => $result['summary'] ?? '',
                'semantic_query' => $result['semantic_query'] ?? null,
                'custom' => $result['custom'] ?? [],
                'fallback_mode' => $result['fallback_mode'] ?? false,
            ], 200);

        } catch (\Throwable $e) {
            Log::error("AI Search Error: " . $e->getMessage());

            $fallbackEntity = 'contacts';
            $q = $incomingQuery;
            $qLower = strtolower($q);
            if (preg_match('/\b(company|companies|revenue|employees|industry|technologies|founded|domain)\b/', $qLower)) {
                $fallbackEntity = 'companies';
            }

            $fallbackFilters = ['contact' => [], 'company' => []];
            if ($fallbackEntity === 'companies') {
                $fallbackFilters['company']['company_keywords'] = ['include' => [$q]];
                if (preg_match('/\$?\s*(\d[\d,\.]*)\s*(m|million|b|billion|k|thousand)/i', $qLower, $m)) {
                    $numStr = str_replace([','], '', $m[1]);
                    $num = (float) $numStr;
                    $unit = strtolower($m[2] ?? '');
                    $mult = $unit === 'k' || $unit === 'thousand' ? 1000 : ($unit === 'm' || $unit === 'million' ? 1000000 : 1000000000);
                    $min = (int) round($num * $mult);
                    if ($min > 0) {
                        $fallbackFilters['company']['annual_revenue'] = ['range' => ['min' => $min]];
                    }
                }
            } else {
                $fallbackFilters['contact']['job_title'] = ['include' => [$q]];
            }

            return response()->json([
                'status' => 'failed',
                'entity' => $fallbackEntity,
                'filters' => $fallbackFilters,
                'summary' => 'The AI is having trouble. A context-aware fallback has been applied.',
                'error_code' => 'AI_INTERNAL_ERROR'
            ], 200);
        }
    }
}
