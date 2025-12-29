<?php

namespace App\Filters\Contracts;

use App\Elasticsearch\ElasticQueryBuilder;

interface FilterHandlerInterface
{
    /**
     * Validate filter values
     *
     * @param  array  $values  Values to validate
     */
    public function validateValues(array $values): bool;

    /**
     * Apply filter values to the query builder
     *
     * @param  ElasticQueryBuilder  $query  Current query builder instance
     * @param  array  $values  Values to filter by
     * @param  bool  $exclude  Whether to exclude (rather than include) results matching these values
     */
    public function apply(ElasticQueryBuilder $query, array $values, string $context = 'company'): ElasticQueryBuilder;

    /**
     * Get possible values for this filter
     *
     * @param  string|null  $search  Search term for filtering values
     * @param  int  $page  Page number for pagination
     * @param  int  $perPage  Number of items per page
     * @return array{
     *     data: array,
     *     metadata: array{
     *         total_count: int,
     *         returned_count: int,
     *         page: int,
     *         per_page: int,
     *         total_pages: int
     *     }
     * }
     */
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array;

    /**
     * Check if exclusion is supported
     */
    public function supportsExclusion(): bool;
}
