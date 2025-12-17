<?php

namespace App\Validators;

use App\Exceptions\QueryValidationException;
use App\Filters\FilterManager;
use Exception;

class SearchQueryValidator
{
    /**
     * @var array Allowed model types
     */
    private const ALLOWED_TYPES = ['company', 'contact'];

    /**
     * @var array Allowed sort fields per model type
     */
    private const ALLOWED_SORT_FIELDS = [
        'company' => ['company', 'website', 'company_linkedin_url', 'employee_count', 'number_of_employees', 'annual_revenue_usd', 'founded_year', 'total_funding_usd'],
        'contact' => ['full_name', 'website', 'title', 'linkedin_url', 'company'],
    ];

    /**
     * @var array Allowed sort directions
     */
    private const ALLOWED_SORT_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected FilterManager $filterManager
    ) {}

    /**
     * Validate search parameters
     *
     * @param  array  $params  Parsed search parameters
     *
     * @throws QueryValidationException
     */
    public function validate(array $params): void
    {
        $errors = [];

        // Validate model type
        if (! empty($params['type']) && ! in_array($params['type'], self::ALLOWED_TYPES)) {
            $errors['type'] = 'Invalid model type. Must be either company or contact.';
        }

        // Validate search term from variables
      // Allow empty searches - users can browse all results without filters or search terms (Apollo.io behavior)
        if (isset($params['variables']['searchTerm'])) {
            $searchTerm = $params['variables']['searchTerm'];
            
            // Only validate length if search term is provided (non-empty)
            if ($searchTerm !== null && $searchTerm !== '') {
                if (strlen($searchTerm) < 2) {
                    $errors['searchTerm'] = 'Search term must be at least 2 characters long.';
                }
                if (strlen($searchTerm) > 100) {
                    $errors['searchTerm'] = 'Search term cannot exceed 100 characters.';
                }
            }
            // Empty search term is allowed - user can browse all results
        }
        // No searchTerm provided is also allowed - user can browse all results

        // Legacy filters array is no longer used by the SPA; canonical filter
        // DSL arrives under variables.filter_dsl and is validated at field level
        // inside the SearchService. We keep the old validation path for safety
        // if filters[] is ever provided directly.
        if (! empty($params['variables']['filters']) && is_array($params['variables']['filters'])) {
            $filterErrors = $this->validateFilters($params['variables']['filters']);
            if (! empty($filterErrors)) {
                $errors['filters'] = $filterErrors;
            }
        }

        // Validate pagination from queryParams
        if (isset($params['queryParams']['page'])) {
            $page = (int) $params['queryParams']['page'];
            if ($page < 1 || $page > 100) {
                $errors['page'] = 'Page must be between 1 and 100.';
            }
        }

        if (isset($params['queryParams']['count'])) {
            $perPage = (int) $params['queryParams']['count'];
            if ($perPage < 1 || $perPage > 100) {
                $errors['count'] = 'Items per page must be between 1 and 100.';
            }
        }

        // Validate sort
        if (! empty($params['sort']['field'])) {
            $sortErrors = $this->validateSort(
                $params['sort'],
                $params['type'] ?? 'company'
            );
            if (! empty($sortErrors)) {
                $errors['sort'] = $sortErrors;
            }
        }

        if (! empty($errors)) {
            throw new QueryValidationException($errors);
        }
    }

    /**
     * Validate filters
     */
    protected function validateFilters(array $filters): array
    {
        $errors = [];

        foreach ($filters as $index => $filter) {
            $filterErrors = [];

            // Check filter structure
            if (empty($filter['type'])) {
                $filterErrors['type'] = 'Filter type is required.';

                continue;
            }

            // Check if filter exists
            $filterModel = $this->filterManager->getFilter($filter['type']);
            if (! $filterModel) {
                $filterErrors['type'] = "Filter '{$filter['type']}' does not exist.";

                continue;
            }

            $handler = $this->filterManager->getHandler($filterModel);

            // Validate values
            if (empty($filter['values'])) {
                $filterErrors['values'] = 'Filter values are required.';
            } else {
                foreach ($filter['values'] as $valueIndex => $value) {
                    // Validate selection type for each value
                    if (! isset($value['selectionType']) ||
                        ! in_array($value['selectionType'], ['INCLUDED', 'EXCLUDED'], true)) {
                        $filterErrors['values'][$valueIndex]['selectionType'] =
                            'Selection type must be either INCLUDED or EXCLUDED.';
                    } elseif ($value['selectionType'] === 'EXCLUDED' && ! $handler->supportsExclusion()) {
                        $filterErrors['values'][$valueIndex]['selectionType'] =
                            'This filter does not support exclusion.';
                    }
                }

                try {
                    $transformedValueData = array_map(fn ($v) => [
                        'id' => $v['id'],
                        'value' => $v['text'],
                        'excluded' => ! $handler->supportsExclusion() ? false : $v['selectionType'] !== 'INCLUDED',
                    ], $filter['values']);
                    if (! $handler->validateValues($transformedValueData)) {
                        if (empty($filterErrors['values'])) {
                            $filterErrors['values'] = 'Invalid filter values.';
                        }
                    }
                } catch (Exception $e) {
                    $filterErrors['values'] = $e->getMessage();
                }
            }

            if (! empty($filterErrors)) {
                $errors[$index] = $filterErrors;
            }
        }

        return $errors;
    }

    /**
     * Validate sort parameters
     *
     * @param  array  $field  Sort field
     * @param  string  $type  Model type
     * @return array Array of validation errors
     */
    protected function validateSort(array $sorts, string $type): array
    {
        $errors = [];

        foreach ($sorts as $index => $sort) {
            $sortErrors = [];

            if (! isset($sort['field']) || ! in_array($sort['field'], self::ALLOWED_SORT_FIELDS[$type])) {
                $sortErrors['field'] = sprintf(
                    'Invalid sort field. Allowed fields for %s are: %s',
                    $type,
                    implode(', ', self::ALLOWED_SORT_FIELDS[$type])
                );
            }

            if (! isset($sort['direction']) || ! in_array($sort['direction'], self::ALLOWED_SORT_DIRECTIONS)) {
                $sortErrors['direction'] = 'Sort direction must be either ASC or DESC.';
            }

            if (! empty($sortErrors)) {
                $errors[$index] = $sortErrors;
            }
        }

        return $errors;
    }
}
