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
    ) {}

    /**
     * Get handler for a specific filter
     */
    public function getHandler(Filter $filter): FilterHandlerInterface
    {
        return $this->factory->make($filter);
    }

    /**
     * Get active filters
     *
     * @return Collection<Filter>
     */
    public function getActiveFilters(): Collection
    {
        return Cache::remember('filters:active', self::CACHE_TTL['filters'], function () {
            return Filter::active()
                ->orderBy('filter_group_id')
                ->orderBy('sort_order')
                ->get();
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
     * @param  array  $filters  Array of [type => string, values => array of [id, text, selectionType]]
     */
    public function applyFilters(ElasticQueryBuilder $query, array $filters): ElasticQueryBuilder
    {
        foreach ($filters as $filter) {
            $filterModel = $this->getFilter($filter['type']);
            $handler = $this->getHandler($filterModel);

            $filterData = [
                'filter_id' => $filter['type'],
                'values' => array_map(fn ($v) => [
                    'id' => $v['id'],
                    'value' => $v['text'],
                    'excluded' => ! $handler->supportsExclusion() ? false : $v['selectionType'] !== 'INCLUDED',
                ], $filter['values']),
            ];

            $query = $handler->apply($query, $filterData['values']);
        }

        return $query;
    }

    /**
     * Get values for a specific filter
     */
    public function getFilterValues(string $filterId, ?string $search = null, int $page = 1, int $perPage = 10): array
    {
        $filter = $this->getFilter($filterId);
        if (! $filter) {
            throw new ModelNotFoundException("Filter not found: {$filterId}");
        }

        $handler = $this->getHandler($filter);

        // Don't cache if searching
        if ($search) {
            return $handler->getValues($search, $page, $perPage);
        }

        // Get cache key and TTL based on filter type
        $cacheKey = $this->getValuesCacheKey($filter, $page, $perPage);
        $cacheTTL = $this->getValuesCacheTTL($filter);

        // Cache values if needed
        return Cache::remember($cacheKey, $cacheTTL, function () use ($handler, $page, $perPage) {
            return $handler->getValues(null, $page, $perPage);
        });
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
