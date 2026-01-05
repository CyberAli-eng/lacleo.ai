<?php

namespace App\Http\Controllers\Api\v1;

use App\Exceptions\QueryValidationException;
use App\Filters\FilterManager;
use App\Http\Controllers\Controller;
use App\Services\SearchService;
use App\Utilities\SearchUrlParser;
use App\Validators\SearchQueryValidator;
use App\Services\FilterRegistry;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\ApiErrorResponse;
use App\Http\Traits\SanitizesPII;

class SearchController extends Controller
{
    use ApiErrorResponse, SanitizesPII;

    public function __construct(
        protected SearchService $searchService,
        protected SearchQueryValidator $searchQueryValidator,
        protected FilterManager $filterManager
    ) {
    }

    /**
     * Search with filters
     */
    public function search(Request $request): JsonResponse
    {


        try {
            $searchParams = $this->prepareSearchParameters($request);

            $cacheKey = 'search:' . sha1(json_encode($searchParams));
            $isPublic = $request->user() === null;

            $results = $isPublic
                ? Cache::remember($cacheKey, now()->addSeconds(60), function () use ($searchParams) {
                    return $this->executeSearch($searchParams);
                })
                : $this->executeSearch($searchParams);

            if (config('app.debug') && ($request->query('debug') === '1' || $request->query('debug') === 'true')) {
                $built = $this->searchService->buildQueryArray(
                    $searchParams['type'],
                    $searchParams['variables']['searchTerm'] ?? null,
                    $searchParams['variables']['filter_dsl'] ?? [],
                    $searchParams['sort'] ?? []
                );
                $results['debug'] = [
                    'params' => $searchParams,
                    'index_used' => $searchParams['type'] === 'company'
                        ? \App\Elasticsearch\IndexResolver::companies()
                        : \App\Elasticsearch\IndexResolver::contacts(),
                    'raw_query' => $built,
                ];
            }

            return response()->json($results);
        } catch (Exception $e) {
            $this->logSanitized('error', 'Search operation failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->handleSearchException($e);
        }
    }

    /**
     * List all available filters
     */
    public function getFilters(): JsonResponse
    {


        $filters = $this->filterManager->getActiveFilters()
            ->groupBy('group')
            ->map(function ($groupFilters, $groupName) {
                return $this->formatFilterGroup($groupFilters, $groupName);
            })
            ->values()
            ->toArray();



        return response()->json(['data' => $filters]);
    }

    /**
     * Get values for a specific filter
     */
    public function getFilterValues(Request $request): JsonResponse
    {
        if (config('app.debug')) {
            Log::debug('Filter values request received', [
                'query_parameters' => $request->query(),
            ]);
        }

        $validator = $this->validateFilterValuesRequest($request);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $requestQuery = $validator->validated();

        // Extract filter_dsl (active filters) from request
        $context = [];
        if ($request->has('filter_dsl')) {
            $raw = $request->input('filter_dsl');
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $context = $decoded;
                }
            } elseif (is_array($raw)) {
                $context = $raw;
            }
        }

        try {
            $values = $this->filterManager->getFilterValues(
                $requestQuery['filter'],
                $requestQuery['q'] ?? null,
                $requestQuery['page'] ?? 1,
                $requestQuery['count'] ?? 10,
                $context,
                $requestQuery['search_type'] ?? null
            );

            if (config('app.debug')) {
                Log::debug('Filter values retrieved successfully', [
                    'filter' => $requestQuery['filter'],
                    'count' => count($values['data'] ?? []),
                ]);
            }

            return response()->json($values);
        } catch (Exception $e) {
            Log::error('Filter values retrieval failed', [
                'filter' => $requestQuery['filter'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->handleFilterValuesException($e);
        }
    }

    /**
     * Debug built ES query for given filters (admin-only)
     */
    public function debugQuery(Request $request): JsonResponse
    {
        $params = $this->prepareSearchParameters($request);
        $query = $this->searchService->buildQueryArray(
            $params['type'],
            $params['variables']['searchTerm'] ?? null,
            $params['variables']['filter_dsl'] ?? [],
            $params['sort'] ?? []
        );

        return response()->json(['query' => $query]);
    }

    /**
     * Prepare search parameters from request
     */
    private function prepareSearchParameters(Request $request): array
    {
        if (config('app.debug')) {
            Log::debug('=== SEARCH REQUEST DEBUG START ===', [
                'query_string' => $request->getQueryString(),
                'full_request' => $request->all(),
                'route_type' => $request->route('type'),
            ]);
        }

        $searchParams = SearchUrlParser::parseQuery($request->getQueryString());
        $searchParams['type'] = $request->route('type', 'company');

        if (isset($searchParams['variables']['variables']) && is_array($searchParams['variables']['variables'])) {
            $searchParams['variables'] = $searchParams['variables']['variables'];
        }

        // Fallbacks: accept simple query parameters when Voyager payload is not used.
        // Map `q` → variables.searchTerm
        if (empty($searchParams['variables']['searchTerm'] ?? null) && isset($searchParams['queryParams']['q'])) {
            $searchParams['variables']['searchTerm'] = (string) $searchParams['queryParams']['q'];
            unset($searchParams['queryParams']['q']);
        }

        // Map `filter_dsl` in queryParams → variables.filter_dsl
        if (empty($searchParams['variables']['filter_dsl'] ?? null) && isset($searchParams['queryParams']['filter_dsl'])) {
            $raw = $searchParams['queryParams']['filter_dsl'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $searchParams['variables']['filter_dsl'] = $decoded;
                } else {
                    Log::warning('Failed to decode filter_dsl JSON', [
                        'raw' => $raw,
                        'json_error' => json_last_error_msg(),
                    ]);
                }
            } elseif (is_array($raw)) {
                $searchParams['variables']['filter_dsl'] = $raw;
            }
            unset($searchParams['queryParams']['filter_dsl']);
        }

        // Map `semantic_query` to variables
        if (isset($searchParams['queryParams']['semantic_query'])) {
            $searchParams['variables']['semantic_query'] = $searchParams['queryParams']['semantic_query'];
        }

        // Ensure filter_dsl is always an array
        if (!isset($searchParams['variables']['filter_dsl']) || !is_array($searchParams['variables']['filter_dsl'])) {
            $searchParams['variables']['filter_dsl'] = [];
        }

        // Normalize empty search term to null (not empty string)
        if (isset($searchParams['variables']['searchTerm']) && $searchParams['variables']['searchTerm'] === '') {
            $searchParams['variables']['searchTerm'] = null;
        }



        try {
            $this->searchQueryValidator->validate($searchParams);
            if (config('app.debug')) {
                Log::debug('Validation passed');
            }
        } catch (QueryValidationException $e) {
            Log::error('Validation failed', [
                'errors' => $e->getErrors(),
                'parsed_params' => $searchParams,
            ]);
            throw $e;
        }

        if (config('app.debug')) {
            Log::debug('=== SEARCH REQUEST DEBUG END ===');
        }

        return $searchParams;
    }

    /**
     * Execute search with parameters
     */
    private function executeSearch(array $params): array
    {

        $page = max(1, min((int) ($params['queryParams']['page'] ?? 1), 100));
        $count = max(1, min((int) ($params['queryParams']['count'] ?? 10), 100));
        $searchTerm = $params['variables']['searchTerm'] ?? null;
        $dsl = $params['variables']['filter_dsl'] ?? [];

        if (empty($searchTerm) && empty($dsl)) {
            return $this->searchService->basicSearch($params['type'], $page, $count);
        }

        return $this->searchService->search(
            $params['type'],
            $searchTerm,
            $dsl,
            $params['sort'] ?? [],
            $page,
            $count,
            $params['variables']['semantic_query'] ?? null,
        );
    }

    /**
     * Format filter group data
     *
     * @param  \Illuminate\Support\Collection  $groupFilters
     */
    private function formatFilterGroup($groupFilters, $groupName): array
    {
        $registry = collect(FilterRegistry::getFilters())->keyBy('id');
        return [
            'group_id' => \Illuminate\Support\Str::slug($groupName),
            'group_name' => $groupName,
            'group_description' => $groupName,
            'filters' => $groupFilters->map(function ($filter) use ($registry) {
                $config = $registry->get($filter->filter_id);
                return [
                    'id' => $filter->filter_id,
                    'name' => $filter->name,
                    'type' => $filter->value_type,
                    'input_type' => $config['input'] ?? $filter->input_type,
                    'is_searchable' => $config['search']['enabled'] ?? $filter->is_searchable,
                    'allows_exclusion' => $filter->allows_exclusion,
                    'supports_value_lookup' => isset($config['preloaded_values']) || ($config['search']['enabled'] ?? false) || $filter->supports_value_lookup,
                    // Server-driven applicability and aggregation
                    'applies_to' => $config['applies_to'] ?? ['company'],
                    'aggregation' => $config['aggregation'] ?? ['enabled' => false],
                    'range' => $config['range'] ?? null,
                    'preloaded_values' => $config['preloaded_values'] ?? null,
                    // Maintain UI compatibility for filter_type without heuristics
                    'filter_type' => (function () use ($config) {
                        $applies = $config['applies_to'] ?? [];
                        if (in_array('contact', $applies, true) && !in_array('company', $applies, true)) {
                            return 'contact';
                        }
                        return 'company';
                    })(),
                ];
            })->values(),
        ];
    }

    /**
     * Validate filter values request
     */
    private function validateFilterValuesRequest(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->query(), [
            'filter' => 'required|string',
            'q' => 'sometimes|nullable|string|max:100',
            'page' => 'sometimes|nullable|integer|min:1',
            'count' => 'sometimes|nullable|integer|between:1,100',
            'level' => 'nullable|string',
            'parents' => 'array',
            'filter_dsl' => 'sometimes|nullable',
            'search_type' => 'sometimes|nullable|string|in:contact,company',
        ]);
    }

    /**
     * Handle search-related exceptions
     */
    private function handleSearchException(Exception $e): JsonResponse
    {
        if ($e instanceof \Elastic\Elasticsearch\Exception\ServerResponseException || stripos($e->getMessage(), 'No alive nodes') !== false) {
            return $this->errorResponse('Search backend is unavailable', 503, ['code' => 'ELASTIC_UNAVAILABLE']);
        }

        if ($e instanceof QueryValidationException) {
            return $this->validationErrorResponse($e->getErrors());
        }

        if ($e instanceof InvalidArgumentException) {
            return $this->errorResponse('Invalid Request', 400, ['detail' => config('app.debug') ? $e->getMessage() : null]);
        }

        return $this->errorResponse('An unexpected error occurred', 500, ['detail' => config('app.debug') ? $e->getMessage() : null]);
    }

    /**
     * Handle filter values exceptions
     */
    private function handleFilterValuesException(Exception $e): JsonResponse
    {
        if ($e instanceof ModelNotFoundException) {
            return response()->json(['message' => 'Filter not found'], 404);
        }

        if ($e instanceof InvalidArgumentException) {
            return response()->json([
                'error' => config('app.debug') ? $e->getMessage() : null,
                'message' => 'Invalid Request',
            ], 400);
        }

        return response()->json([
            'message' => 'Internal Server Error',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}
