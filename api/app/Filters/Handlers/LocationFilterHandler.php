<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LocationFilterHandler extends AbstractFilterHandler
{
    /**
     * Get possible values for this filter
     */
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array
    {
        $targetModel = $this->filter->getTargetModelOrFail();
        $elastic = $targetModel::elastic();
        $settings = $this->filter->settings;
        $levels = $settings['hierarchy_levels'] ?? [];

        if (empty($levels)) {
            throw new InvalidArgumentException('Hierarchy levels not configured');
        }

        // Get and validate current level
        $searchLevel = request('level', 'country');
        if (! isset($levels[$searchLevel])) {
            throw new InvalidArgumentException("Invalid hierarchy level: {$searchLevel}");
        }

        $field = $levels[$searchLevel]['field'];
        $parentValues = request('parents', []);

        try {
            foreach ($levels as $level => $config) {
                if ($level === $searchLevel) {
                    break;
                }
                if (isset($parentValues[$level])) {
                    $elastic->must($config['field'], $parentValues[$level], 'term');
                }
            }

            if (! empty($search) && $this->filter->is_searchable) {
                $elastic->must([
                    'bool' => [
                        'should' => [
                            [
                                'prefix' => [
                                    $field => [
                                        'value' => $search,
                                        'boost' => 2.0,
                                    ],
                                ],
                            ],
                            [
                                'prefix' => [
                                    $field.'.lowercase' => [
                                        'value' => strtolower($search),
                                        'boost' => 1.5,
                                    ],
                                ],
                            ],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ]);
            }

            $elastic->termsAggregation(
                'distinct_values',
                $field,
                [
                    'size' => 10000,
                    'order' => [
                        '_key' => 'asc',
                    ],
                    'min_doc_count' => 1,
                ]
            );

            foreach ($levels as $level => $config) {
                $elastic->aggregations([
                    "has_{$level}" => [
                        'filter' => [
                            'exists' => [
                                'field' => $config['field'],
                            ],
                        ],
                    ],
                ]);
            }

            $result = $elastic->get();

            $availableLevels = [];
            foreach ($levels as $level => $config) {
                $docCount = $result['aggregations']["has_{$level}"]['doc_count'] ?? 0;
                if ($docCount > 0) {
                    $availableLevels[] = $level;
                }
            }

            $nextLevel = $this->getNextAvailableLevel($searchLevel, $availableLevels);
            $values = collect($result['aggregations']['distinct_values']['buckets'] ?? [])
                ->map(function ($bucket) use ($searchLevel, $nextLevel) {
                    return [
                        'id' => $bucket['key'],
                        'name' => $bucket['key'],
                        'count' => $bucket['doc_count'],
                        'level' => $searchLevel,
                        'has_children' => ! empty($nextLevel),
                        'next_level' => $nextLevel,
                    ];
                })
                ->values()
                ->toArray();

            return $this->paginateResults($values, $page, $perPage);

        } catch (Exception $e) {
            Log::error('Error in hierarchical filter getValues', [
                'filter' => $this->filter->filter_id,
                'level' => $searchLevel,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

    }

    /**
     * Apply the location filter to the query
     */
    public function apply(ElasticQueryBuilder $query, array $values): ElasticQueryBuilder
    {
        if (! $this->validateValues($values)) {
            throw new InvalidArgumentException("Invalid values for filter: {$this->filter->name}");
        }

        $settings = $this->filter->settings;
        $levels = $settings['hierarchy_levels'] ?? [];

        $shouldClauses = [];
        foreach ($values as $value) {
            if (! isset($value['level'], $value['value']) || ! isset($levels[$value['level']])) {
                continue;
            }

            $field = $levels[$value['level']]['field'];
            $shouldClauses[] = ['term' => [$field => $value['value']]];
        }

        if (empty($shouldClauses)) {
            return $query;
        }

        $clause = count($shouldClauses) === 1
            ? $shouldClauses[0]
            : ['bool' => ['should' => $shouldClauses]];

        if ($value['excluded']) {
            $query->mustNot($clause);
        } else {
            $query->must($clause);
        }

        return $query;
    }

    /**
     * Validate location values
     */
    public function validateValues(array $values): bool
    {
        if (empty($values)) {
            return false;
        }

        $settings = $this->filter->settings;
        $levels = $settings['hierarchy_levels'] ?? [];

        foreach ($values as $value) {
            if (! isset($value['level'], $value['value']) ||
                ! isset($levels[$value['level']]) ||
                ! is_string($value['value']) ||
                empty(trim($value['value']))
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get next available level
     */
    protected function getNextAvailableLevel(string $currentLevel, array $availableLevels): ?string
    {
        $currentIndex = array_search($currentLevel, $availableLevels);
        if ($currentIndex === false || ! isset($availableLevels[$currentIndex + 1])) {
            return null;
        }

        return $availableLevels[$currentIndex + 1];
    }
}
