<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;

class ElasticsearchFilterHandler extends AbstractFilterHandler
{
    public function validateValues(array $values): bool
    {
        // Values are already normalized by FilterManager to ['include' => [], 'exclude' => [], 'range' => [], 'presence' => ...]
        if (empty($values)) {
            return false;
        }

        // Basic structure check
        $hasAny = !empty($values['include']) ||
            !empty($values['exclude']) ||
            !empty($values['range']) ||
            !empty($values['presence']);

        return $hasAny;
    }

    /**
     * Get the appropriate field for the current context or target model
     */
    protected function getField(string $context): string
    {
        $fields = $this->filter->settings['fields'] ?? [];

        // Try exact context match
        if (isset($fields[$context]) && !empty($fields[$context])) {
            return $fields[$context][0];
        }

        // Fallback: try to derive context from target model if not provided or found
        $targetModel = $this->filter->settings['target_model'] ?? null;
        if ($targetModel === \App\Models\Contact::class) {
            return $fields['contact'][0] ?? $this->filter->elasticsearch_field ?? '';
        }

        return $fields['company'][0] ?? $this->filter->elasticsearch_field ?? '';
    }

    /**
     * Get possible values for this filter
     */
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10, array $context = []): array
    {
        $targetModel = $this->filter->getTargetModelOrFail();
        $elastic = $targetModel::elastic();

        $settings = $this->filter->settings;
        $fieldType = $this->filter->type ?? ($settings['field_type'] ?? 'text');

        // Determine field based on target model context
        $context = ($targetModel === \App\Models\Contact::class) ? 'contact' : 'company';
        $field = $this->getField($context);

        if (empty($field)) {
            return $this->emptyPaginatedResponse($page, $perPage);
        }

        // For suggestions, we want to filter the TERMS, not just the documents.
        // But filtering documents helps performance.
        if (!empty($search)) {
            // Keep doc filtering for performance
            if ($fieldType === 'keyword') {
                $elastic->must([
                    'bool' => [
                        'should' => [
                            ['prefix' => [$field => $search]],
                            ['prefix' => [$field . '.lowercase' => strtolower($search)]],
                            ['wildcard' => [$field => "*{$search}*"]],
                            ['wildcard' => [$field . '.lowercase' => "*" . strtolower($search) . "*"]],
                        ],
                    ],
                ]);
            } else {
                // For text fields, ensure we match
                $searchFields = $this->filter->settings['search_fields'] ?? [];
                if (empty($searchFields))
                    $searchFields = [$field];

                $elastic->multiMatch(
                    query: $search,
                    fields: $searchFields,
                    options: ['type' => 'phrase_prefix']
                );
            }
        }

        $aggParams = [
            'size' => 100, // Limit suggestion count
            'order' => ['_key' => 'asc'],
        ];

        // Filter the actual terms returned in the bucket
        if (!empty($search)) {
            // Regex for case-insensitive substring match: .*search.*
            // Note: This runs on the term values. If using .keyword field, they might be mixed case.
            // Elasticsearch regex is case-sensitive on terms.
            // If the field is analyzed as generic keyword, it might be exact.
            // Using a safe wildcard include (.*foo.*)
            $aggParams['include'] = '.*' . preg_quote($search) . '.*';
        }

        $aggField = $fieldType === 'keyword' ? $field : $field . '.keyword';
        $nestedPath = null;
        $nestedPaths = ['emails', 'phone_numbers', 'company_obj.emails', 'company_obj.phone_numbers'];
        foreach ($nestedPaths as $path) {
            if ($field === $path || str_starts_with($field, $path . '.')) {
                $nestedPath = $path;
                break;
            }
        }

        if ($nestedPath) {
            $elastic->aggregations([
                'nested_values' => [
                    'nested' => ['path' => $nestedPath],
                    'aggs' => [
                        'distinct_values' => [
                            'terms' => array_merge(['field' => $aggField], $aggParams)
                        ]
                    ]
                ]
            ]);
        } else {
            $elastic->termsAggregation('distinct_values', $aggField, $aggParams);
        }

        $result = $elastic->paginate($page, $perPage);

        $buckets = $nestedPath
            ? ($result['aggregations']['nested_values']['distinct_values']['buckets'] ?? [])
            : ($result['aggregations']['distinct_values']['buckets'] ?? []);

        $values = collect($buckets)
            ->map(fn($bucket) => [
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
    public function apply(ElasticQueryBuilder $query, array $values, string $context = 'company'): ElasticQueryBuilder
    {
        $field = $this->getField($context);
        if (empty($field)) {
            return $query;
        }

        $include = $values['include'] ?? [];
        $exclude = $values['exclude'] ?? [];
        $range = $values['range'] ?? null;
        $presence = $values['presence'] ?? null;
        $operator = $values['operator'] ?? 'and';

        if ($presence === 'known') {
            $query->filter($this->wrapIfNested($field, ['exists' => ['field' => $field]]));
        } elseif ($presence === 'unknown') {
            $query->mustNot($this->wrapIfNested($field, ['exists' => ['field' => $field]]));
        }

        // Range
        if (is_array($range) && (isset($range['min']) || isset($range['max']))) {
            $clause = [
                'range' => [
                    $field => array_filter([
                        'gte' => isset($range['min']) ? (float) $range['min'] : null,
                        'lte' => isset($range['max']) ? (float) $range['max'] : null,
                    ])
                ]
            ];
            $query->filter($this->wrapIfNested($field, $clause));
        }

        // Include values
        if (!empty($include)) {
            $this->applyInclude($query, $field, $include, $operator);
        }

        // Exclude values
        if (!empty($exclude)) {
            $this->applyExclude($query, $field, $exclude);
        }

        return $query;
    }

    protected function applyInclude(ElasticQueryBuilder $query, string $field, array $values, string $operator): void
    {
        $settings = $this->filter->settings;
        $fieldType = $this->filter->type ?? ($settings['field_type'] ?? 'text');
        if ($fieldType === 'keyword') {
            $query->filter($this->wrapIfNested($field, ['terms' => [$field => $values]]));
            return;
        }
        if ($operator === 'or') {
            $clause = [
                'bool' => [
                    'should' => array_map(fn($v) => ['match_phrase' => [$field => $v]], $values),
                    'minimum_should_match' => 1,
                ],
            ];
            $query->filter($this->wrapIfNested($field, $clause));
        } else {
            foreach ($values as $v) {
                $query->filter($this->wrapIfNested($field, ['match_phrase' => [$field => $v]]));
            }
        }
    }

    protected function applyExclude(ElasticQueryBuilder $query, string $field, array $values): void
    {
        $settings = $this->filter->settings;
        $fieldType = $this->filter->type ?? ($settings['field_type'] ?? 'text');
        if ($fieldType === 'keyword') {
            $query->mustNot($this->wrapIfNested($field, ['terms' => [$field => $values]]));
            return;
        }
        foreach ($values as $v) {
            $query->mustNot($this->wrapIfNested($field, ['match_phrase' => [$field => $v]]));
        }
    }
}
