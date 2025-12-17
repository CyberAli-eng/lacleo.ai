<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;

class ElasticsearchFilterHandler extends AbstractFilterHandler
{
    /**
     * Get possible values for this filter
     */
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array
    {
        $targetModel = $this->filter->getTargetModelOrFail();
        $elastic = $targetModel::elastic();

        $settings = $this->filter->settings;
        $fieldType = $settings['field_type'] ?? 'text';

        $searchFields = $settings['search_fields'] ?? [];

        if (! empty($search)) {
            if ($fieldType === 'keyword') {
                $elastic->must([
                    'bool' => [
                        'should' => [
                            [
                                'prefix' => [
                                    $this->filter->elasticsearch_field => $search,
                                ],
                            ],
                            [
                                'prefix' => [
                                    $this->filter->elasticsearch_field.'.lowercase' => strtolower($search),
                                ],
                            ],
                        ],
                    ],
                ]);
            } else {
                $searchFields = $this->filter->settings['search_fields'] ?? [];
                $elastic->multiMatch(
                    query: $search,
                    fields: $searchFields,
                    options: [
                        'type' => 'best_fields',
                        'operator' => 'and',
                        'minimum_should_match' => '70%',
                    ]
                );
            }
        }

        $elastic->termsAggregation(
            'distinct_values',
            $fieldType === 'keyword'
                ? $this->filter->elasticsearch_field
                : $this->filter->elasticsearch_field.'.keyword',
            [
                'size' => 10000,
                'order' => ['_key' => 'asc'],
            ]
        );

        $result = $elastic->paginate($page, $perPage);

        $values = collect($result['aggregations']['distinct_values']['buckets'] ?? [])
            ->map(fn ($bucket) => [
                'id' => $bucket['key'],
                'name' => $bucket['key'],
            ])
            ->values()
            ->toArray();

        return $this->paginateResults($values, $page, $perPage);
    }

    /**
     * Apply the elasticsearch filter to the query
     */
    public function apply(ElasticQueryBuilder $query, array $values): ElasticQueryBuilder
    {
        $included = array_column(array_filter($values, fn ($v) => ! $v['excluded']), 'value');
        $excluded = array_column(array_filter($values, fn ($v) => $v['excluded']), 'value');

        if (! empty($included)) {
            $this->applyFilter($query, $included, 'must');
        }
        if (! empty($excluded)) {
            $this->applyFilter($query, $excluded, 'mustNot');
        }

        return $query;
    }

    protected function applyFilter(ElasticQueryBuilder $query, array $values, string $type): void
    {
        $field = $this->filter->elasticsearch_field;
        $fieldType = $this->filter->settings['field_type'] ?? 'text';

        if ($this->filter->filter_id === 'job_title') {
            $keywords = [];
            foreach ($values as $v) {
                foreach (preg_split('/\s*,\s*/', (string) $v) as $k) {
                    $k = trim($k);
                    if ($k !== '') {
                        $keywords[] = $k;
                    }
                }
            }

            if (empty($keywords)) {
                return;
            }

            $fields = [
                'title^4',
                'job_title^3',
                'normalized_title^6',
                'title_keywords^8',
            ];

            $should = [];
            foreach ($keywords as $k) {
                $should[] = [
                    'multi_match' => [
                        'query' => $k,
                        'fields' => $fields,
                        'type' => 'best_fields',
                        'fuzziness' => 'AUTO',
                        'operator' => 'or',
                    ],
                ];
                $should[] = ['match' => ['title_synonyms' => $k]];
            }

            $query->$type([
                'bool' => [
                    'should' => $should,
                    'minimum_should_match' => 1,
                ],
            ]);

            return;
        }

        // Number fields support ranges and exact terms
        if ($fieldType === 'number') {
            $should = [];
            foreach ($values as $v) {
                $val = is_string($v) ? trim($v) : $v;
                if (is_string($val)) {
                    if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $val, $m)) {
                        $should[] = ['range' => [$field => ['gte' => (int) $m[1], 'lte' => (int) $m[2]]]];

                        continue;
                    }
                    if (preg_match('/^>=\s*(\d+)$/', $val, $m)) {
                        $should[] = ['range' => [$field => ['gte' => (int) $m[1]]]];

                        continue;
                    }
                    if (preg_match('/^<=\s*(\d+)$/', $val, $m)) {
                        $should[] = ['range' => [$field => ['lte' => (int) $m[1]]]];

                        continue;
                    }
                    if (preg_match('/^\d+$/', $val)) {
                        $should[] = ['term' => [$field => (int) $val]];

                        continue;
                    }
                }
                if (is_numeric($val)) {
                    $should[] = ['term' => [$field => (int) $val]];
                }
            }

            if (! empty($should)) {
                $query->$type([
                    'bool' => [
                        'should' => $should,
                        'minimum_should_match' => 1,
                    ],
                ]);
            }

            return;
        }

        $clause = $fieldType === 'keyword'
            ? ['terms' => [$field => $values]]
            : [
                'bool' => [
                    'should' => array_map(
                        fn ($value) => [
                            'match_phrase' => [
                                $field => $value,
                            ],
                        ],
                        $values
                    ),
                    'minimum_should_match' => 1,
                ],
            ];

        $query->$type($clause);
    }

    /**
     * Validate the input values
     */
    public function validateValues(array $values): bool
    {
        if (empty($values)) {
            return false;
        }

        return collect($values)->every(function ($value) {
            $v = $value['value'] ?? null;
            if (is_null($v)) {
                return false;
            }
            if (is_numeric($v)) {
                return true;
            }
            if (is_string($v)) {
                return trim($v) !== '';
            }

            return false;
        });
    }
}
