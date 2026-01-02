<?php

namespace App\Filters\Handlers;

use App\Filters\Contracts\FilterHandlerInterface;
use App\Models\Filter;

abstract class AbstractFilterHandler implements FilterHandlerInterface
{
    public function __construct(
        protected \App\Models\Filter $filter,
        protected ?\App\Filters\FilterManager $manager = null
    ) {
    }

    public function supportsExclusion(): bool
    {
        return $this->filter->allows_exclusion;
    }

    abstract public function getValues(?string $search = null, int $page = 1, int $perPage = 10, array $context = []): array;

    /**
     * Paginate an array of results
     */
    protected function paginateResults(array $results, int $page, int $perPage): array
    {
        $total = count($results);
        $totalPages = max(ceil($total / $perPage), 1);
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $items = array_slice($results, $offset, $perPage);

        return [
            'data' => $items,
            'metadata' => [
                'total_count' => $total,
                'returned_count' => count($items),
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Get empty paginated response
     */
    protected function emptyPaginatedResponse(int $page = 1, int $perPage = 10): array
    {
        return [
            'data' => [],
            'metadata' => [
                'total_count' => 0,
                'returned_count' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0,
            ],
        ];
    }

    /**
     * Wrap query in nested block if the field is a known nested field.
     */
    protected function wrapIfNested(string $field, array $query): array
    {
        $nestedPaths = ['emails', 'phone_numbers', 'company_obj.emails', 'company_obj.phone_numbers'];

        foreach ($nestedPaths as $path) {
            if ($field === $path || str_starts_with($field, $path . '.')) {
                return [
                    'nested' => [
                        'path' => $path,
                        'query' => $query
                    ]
                ];
            }
        }

        return $query;
    }

    /**
     * Each handler must implement its own validation logic
     */
    abstract public function validateValues(array $values): bool;
}
