<?php

namespace App\Filters;

use App\Elasticsearch\ElasticQueryBuilder;
use App\Filters\Contracts\FilterHandlerInterface;
use App\Models\Filter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FilterManager
{
    /**
     * Cache TTL in minutes for different types
     */
    protected const CACHE_TTL = [
        'filters' => 60,          // Active filters list
        'predefined' => 1440,     // Predefined values (24 hours)
        'location' => 1440,       // Location hierarchies (24 hours)
        'elasticsearch' => 30,     // Dynamic values (30 minutes)
    ];

    public function __construct(
        protected FilterHandlerFactory $factory
    ) {
    }

    /**
     * Get handler for a specific filter
     */
    public function getHandler(Filter $filter): FilterHandlerInterface
    {
        return $this->factory->make($filter, $this);
    }

    /**
     * Get active filters
     *
     * @return Collection<Filter>
     */
    public function getActiveFilters(): Collection
    {
        // Use Registry as the single source of truth
        $configs = \App\Services\FilterRegistry::getFilters();

        return collect($configs)->map(function ($config) {
            $attributes = [
                'filter_id' => $config['id'],
                'name' => $config['label'],
                'group' => $config['group'], // Group Name
                'filter_group_id' => 1, // Placeholder
                'value_source' => $config['data_source'],
                'value_type' => $config['type'],
                'input_type' => $config['input'],
                'is_searchable' => $config['search']['enabled'] ?? false,
                'allows_exclusion' => $config['filtering']['supports_exclusion'] ?? false,
                'settings' => [
                    'fields' => $config['fields'],
                    'search_fields' => $config['search']['suggest_fields'] ?? [],
                    'target_model' => in_array('company', $config['applies_to']) ? \App\Models\Company::class : (in_array('contact', $config['applies_to']) ? \App\Models\Contact::class : \App\Models\Company::class),
                    'filtering' => $config['filtering'] ?? ['mode' => 'terms', 'supports_exclusion' => false],
                ],
                'sort_order' => $config['sort_order'],
                'is_active' => $config['active'],
                'supports_value_lookup' => in_array($config['data_source'], ['elasticsearch', 'predefined']),
                'filter_type' => $config['type'],
                'range' => $config['range'] ?? null,
                'hint' => $config['hint'] ?? null,
            ];

            $filter = new Filter($attributes);
            $filter->type = $config['type']; // Add type for factory dispatch
            return $filter;
        });
    }

    /**
     * Get a specific filter by ID
     */
    public function getFilter(string $filterId): ?Filter
    {
        return $this->getActiveFilters()->firstWhere('filter_id', $filterId);
    }

    /**
     * Apply multiple filters to a query
     *
     * @param  array  $filters  Array of [filter_id => value] (DSL)
     */
    public function applyFilters(ElasticQueryBuilder $query, array $filters, string $context = 'company'): ElasticQueryBuilder
    {
        $orderedFilters = $this->sortFilters($filters);

        foreach ($orderedFilters as $filterId => $value) {
            $filterModel = $this->getFilter($filterId);
            if (!$filterModel) {
                \Log::warning('FilterManager: Unknown filter ID ignored', ['filter_id' => $filterId]);
                continue;
            }

            $normalized = $this->normalizeFilterValue($value);

            // Enforce exclusion support
            if (!empty($normalized['exclude'] ?? []) && !($filterModel->allows_exclusion ?? false)) {
                \Log::warning('FilterManager: Exclusions not supported for filter, removing', ['filter_id' => $filterId]);
                $normalized['exclude'] = [];
            }

            $handler = $this->getHandler($filterModel);
            $query = $handler->apply($query, $normalized, $context);
        }

        return $query;
    }

    protected function sortFilters(array $filters): array
    {
        // Priority: boolean > range > terms (keyword) > term (text/direct)
        $priorityMap = [
            'boolean' => 1,
            'range' => 2,
            'date' => 2,
            'keyword' => 3,
            'text' => 4,
            'direct' => 4,
        ];

        $sortedKeys = array_keys($filters);
        usort($sortedKeys, function ($a, $b) use ($priorityMap) {
            $filterA = $this->getFilter($a);
            $filterB = $this->getFilter($b);

            $typeA = $filterA->type ?? 'text';
            $typeB = $filterB->type ?? 'text';

            $pA = $priorityMap[$typeA] ?? 10;
            $pB = $priorityMap[$typeB] ?? 10;

            return $pA <=> $pB;
        });

        $sortedFilters = [];
        foreach ($sortedKeys as $key) {
            $sortedFilters[$key] = $filters[$key];
        }

        return $sortedFilters;
    }

    protected function normalizeFilterValue(mixed $value): array
    {
        $out = [
            'include' => [],
            'exclude' => [],
            'range' => null,
            'presence' => null,
            'operator' => null,
        ];

        if (is_array($value)) {
            if (isset($value['include']) && is_array($value['include'])) {
                $out['include'] = array_values(array_filter($value['include'], fn($v) => is_string($v) || is_numeric($v)));
            }
            if (isset($value['exclude']) && is_array($value['exclude'])) {
                $out['exclude'] = array_values(array_filter($value['exclude'], fn($v) => is_string($v) || is_numeric($v)));
            }
            if (isset($value['range']) && is_array($value['range'])) {
                $min = $value['range']['min'] ?? null;
                $max = $value['range']['max'] ?? null;
                $out['range'] = ['min' => is_numeric($min) ? (float) $min : null, 'max' => is_numeric($max) ? (float) $max : null];
            }
            if (isset($value['presence'])) {
                $presence = $value['presence'];
                if (in_array($presence, ['any', 'known', 'unknown'], true)) {
                    $out['presence'] = $presence;
                }
            }
            if (isset($value['operator'])) {
                $op = $value['operator'];
                if (in_array($op, ['and', 'or'], true)) {
                    $out['operator'] = $op;
                }
            }
        } elseif (is_string($value) || is_numeric($value)) {
            $out['include'] = [$value];
        }

        return $out;
    }

    /**
     * Get values for a specific filter
     */
    public function getFilterValues(?string $filterId, ?string $search = null, int $page = 1, int $perPage = 10, array $context = [], ?string $searchType = null): array
    {
        // Alias common mismatched IDs
        $aliases = [
            'company_technologies' => 'technologies',
            'contact_technologies' => 'technologies',
            'tech' => 'technologies',
            'company_location' => 'company_country',
            'contact_location' => 'contact_country',
        ];
        $actualId = $aliases[$filterId] ?? $filterId;

        $filter = $this->getFilter($actualId);
        if (!$filter) {
            $filter = $this->getFilter($filterId);
            if (!$filter) {
                throw new ModelNotFoundException("Filter not found: {$filterId} (aliased to {$actualId})");
            }
        }

        // Get filter config from registry
        $configs = \App\Services\FilterRegistry::getFilters();
        $filterConfig = collect($configs)->firstWhere('id', $filter->filter_id);

        // If no search query and filter has preloaded values, return them
        if (!$search && isset($filterConfig['preloaded_values']) && !empty($filterConfig['preloaded_values'])) {
            // Get aggregation counts if enabled
            $counts = [];
            if (isset($filterConfig['aggregation']['enabled']) && $filterConfig['aggregation']['enabled']) {
                try {
                    $counts = $this->getAggregationCounts($filter, $filterConfig, $context, $searchType);
                } catch (\Throwable $e) {
                    \Log::warning('Failed to get aggregation counts for filter', [
                        'filter_id' => $filter->filter_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Merge preloaded values with counts
            $preloadedData = collect($filterConfig['preloaded_values'])->map(function ($item) use ($counts) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'count' => $counts[$item['id']] ?? $item['count'] ?? null,
                ];
            })->values()->toArray();

            return [
                'data' => $preloadedData,
                'meta' => [
                    'current_page' => 1,
                    'per_page' => count($preloadedData),
                    'total' => count($preloadedData),
                    'has_more' => false,
                    'preloaded' => true,
                ],
            ];
        }

        $handler = $this->getHandler($filter);

        // Don't cache if searching or if context (active filters) is provided
        if ($search || !empty($context)) {
            return $handler->getValues($search, $page, $perPage, $context, $searchType);
        }

        // Get cache key and TTL based on filter type
        $cacheKey = $this->getValuesCacheKey($filter, $page, $perPage);
        $cacheTTL = $this->getValuesCacheTTL($filter);

        // Cache values if needed
        return Cache::remember($cacheKey, $cacheTTL, function () use ($handler, $page, $perPage) {
            return $handler->getValues(null, $page, $perPage, [], null);
        });
    }

    /**
     * Get aggregation counts for filter values
     */
    protected function getAggregationCounts(Filter $filter, array $filterConfig, array $context = [], ?string $searchType = null): array
    {
        $appliesTo = $searchType ?: ($filterConfig['applies_to'][0] ?? 'company');
        $targetModel = $appliesTo === 'contact' ? \App\Models\Contact::class : \App\Models\Company::class;
        $fields = $filterConfig['fields'][$appliesTo] ?? $filterConfig['fields']['company'] ?? [];

        if (empty($fields)) {
            return [];
        }

        $field = $fields[0]; // Use first field for aggregation
        $aggSize = $filterConfig['aggregation']['size'] ?? 50;

        // Determine the actual field to aggregate on
        $aggField = $field;

        // If it's a text field, we usually need .keyword. 
        // If it's keyword, we use it directly.
        // For safety, let's try to detect if it's already a keyword field from the filter type
        $isKeyword = ($filter->value_type === 'keyword' || $filter->value_type === 'boolean');
        if (!$isKeyword && !str_ends_with($aggField, '.keyword')) {
            $aggField .= '.keyword';
        }

        try {
            $queryBuilder = new ElasticQueryBuilder($targetModel);

            // Extract the correct bucket from the DSL (contact/company)
            // The frontend passes a canonical DSL: { contact: {...}, company: {...} }
            $contactContext = $context['contact'] ?? [];
            $companyContext = $context['company'] ?? [];

            // If the context is passed as a flat array (from older code/tests), try to use it directly
            if (empty($contactContext) && empty($companyContext) && !empty($context)) {
                // Heuristic: if no contact/company keys, assume it's a flat context for the current target
                if (($filterConfig['applies_to'][0] ?? '') === 'contact') {
                    $contactContext = $context;
                } else {
                    $companyContext = $context;
                }
            }

            // Exclude current filter from its own facet calculation
            unset($contactContext[$filter->filter_id]);
            unset($companyContext[$filter->filter_id]);

            if (!empty($contactContext)) {
                $this->applyFilters($queryBuilder, $contactContext, 'contact');
            }
            if (!empty($companyContext)) {
                $this->applyFilters($queryBuilder, $companyContext, 'company');
            }

            $nestedPath = null;
            $nestedPaths = ['emails', 'phone_numbers', 'company_obj.emails', 'company_obj.phone_numbers'];
            foreach ($nestedPaths as $path) {
                if ($aggField === $path || str_starts_with($aggField, $path . '.')) {
                    $nestedPath = $path;
                    break;
                }
            }

            $aggs = [
                'values' => [
                    'terms' => [
                        'field' => $aggField,
                        'size' => $aggSize,
                    ],
                ],
            ];

            if ($nestedPath) {
                $query = [
                    'query' => $queryBuilder->toArray()['query'] ?? ['match_all' => (object) []],
                    'size' => 0,
                    'aggs' => [
                        'nested_values' => [
                            'nested' => ['path' => $nestedPath],
                            'aggs' => $aggs
                        ]
                    ]
                ];
            } else {
                $query = [
                    'query' => $queryBuilder->toArray()['query'] ?? ['match_all' => (object) []],
                    'size' => 0,
                    'aggs' => $aggs
                ];
            }

            $result = $targetModel::searchInElastic($query);

            $buckets = $nestedPath
                ? ($result['aggregations']['nested_values']['values']['buckets'] ?? [])
                : ($result['aggregations']['values']['buckets'] ?? []);

            $counts = [];
            foreach ($buckets as $bucket) {
                $counts[$bucket['key']] = $bucket['doc_count'];
            }

            return $counts;
        } catch (\Throwable $e) {
            \Log::error('Failed to get aggregation counts', [
                'filter_id' => $filter->filter_id,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Generate cache key for filter values
     */
    protected function getValuesCacheKey(Filter $filter, int $page, int $perPage): string
    {
        return "filters:values:{$filter->filter_id}:{$page}:{$perPage}";
    }

    /**
     * Get cache TTL based on filter type
     */
    protected function getValuesCacheTTL(Filter $filter): int
    {
        return match ($filter->value_source) {
            'predefined' => self::CACHE_TTL['predefined'],
            'specialized' => match ($filter->value_type) {
                    'location' => self::CACHE_TTL['location'],
                    default => 0
                },
            'elasticsearch' => self::CACHE_TTL['elasticsearch'],
            default => 0 // Don't cache other types
        };
    }
}