<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;

class FacetFilterHandler extends AbstractFilterHandler
{
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10, array $context = []): array
    {
        $targetModel = $this->filter->getTargetModelOrFail();
        $elastic = $targetModel::elastic();

        // Default to company context for values if not specified, 
        // but getValues usually comes from a specific endpoint context.
        // We might need to know the context here too. 
        // For now, assume the target model in filter settings is correct.

        // However, the registry has 'fields' per context.
        // We'll use the 'company' fields as default for value lookup if available, or first available.
        $fields = $this->filter->settings['fields']['company'] ??
            $this->filter->settings['fields']['contact'] ?? [];

        $field = $fields[0] ?? null;

        if (!$field) {
            return $this->emptyPaginatedResponse($page, $perPage);
        }

        if (!empty($search)) {
            $elastic->must([
                'bool' => [
                    'should' => [
                        ['prefix' => [$field => $search]],
                        ['prefix' => [$field . '.lowercase' => strtolower($search)]],
                    ],
                    'minimum_should_match' => 1
                ]
            ]);
        }

        // Use the field itself for aggregation. 
        // Ensure it's a keyword field (usually ends in .keyword if text, or is keyword type)
        // The registry says type="keyword", so we assume the field is aggregatable.
        $aggField = $field;
        // Check if we need .keyword suffix? The prompt says "keyword fields only for filters".
        // We assume the schema 'fields' point to the keyword version if needed.

        $aggParams = [
            'size' => 100, // Reduced size for performance
            'order' => ['_key' => 'asc'],
        ];

        // Filter the actual terms returned in the bucket using Regex
        // This ensures that even if we match documents, we only show RELEVANT terms (autocomplete behavior)
        if (!empty($search)) {
            // Regex for case-insensitive substring match: .*search.*
            // Elasticsearch regex on keyword fields is efficient enough for this scale.
            $aggParams['include'] = '.*' . preg_quote($search) . '.*';
        }

        $elastic->termsAggregation(
            'distinct_values',
            $aggField,
            $aggParams
        );

        $result = $elastic->paginate($page, $perPage);

        $values = collect($result['aggregations']['distinct_values']['buckets'] ?? [])
            ->map(fn($bucket) => [
                'id' => $bucket['key'],
                'name' => $bucket['key'],
                'count' => $bucket['doc_count'] ?? 0,
            ])
            ->values()
            ->toArray();

        return $this->paginateResults($values, $page, $perPage);
    }

    public function validateValues(array $values): bool
    {
        return true;
    }

    public function apply(ElasticQueryBuilder $query, array $values, string $context = 'company'): ElasticQueryBuilder
    {
        $fields = $this->filter->settings['fields'][$context] ?? [];
        if (empty($fields)) {
            return $query;
        }
        $field = $fields[0];

        $included = $values['include'] ?? [];
        $excluded = $values['exclude'] ?? [];
        $presence = $values['presence'] ?? null;
        $operator = $values['operator'] ?? 'and';

        if ($presence === 'known') {
            $this->applyPresence($query, $field, true);
        } elseif ($presence === 'unknown') {
            $this->applyPresence($query, $field, false);
        }

        if (!empty($included)) {
            if ($operator === 'or') {
                $query->should(['terms' => [$field => $included]]);
            } else {
                $query->must(['terms' => [$field => $included]]);
            }
        }

        if (!empty($excluded)) {
            $query->mustNot(['terms' => [$field => $excluded]]);
        }

        return $query;
    }

    protected function applyPresence(ElasticQueryBuilder $query, string $field, bool $exists): void
    {
        $nestedPath = $this->isNestedField($field);

        if ($nestedPath) {
            $inner = ['exists' => ['field' => $field]];
            if ($exists) {
                $query->nested($nestedPath, $inner);
            } else {
                $query->mustNot([
                    'nested' => [
                        'path' => $nestedPath,
                        'query' => $inner
                    ]
                ]);
            }
        } else {
            if ($exists) {
                $query->must(['exists' => ['field' => $field]]);
            } else {
                $query->mustNot(['exists' => ['field' => $field]]);
            }
        }
    }

    protected function isNestedField(string $field): ?string
    {
        $nestedFields = [
            'emails' => 'emails',
            'phone_numbers' => 'phone_numbers',
            'funding' => 'funding',
            'social_media' => 'social_media',
            'location' => 'location',
            'company_obj' => 'company_obj', // Fix for company filters on contact search
        ];

        foreach ($nestedFields as $prefix => $path) {
            if ($field === $prefix || str_starts_with($field, $prefix . '.')) {
                return $path;
            }
        }

        return null;
    }
}
