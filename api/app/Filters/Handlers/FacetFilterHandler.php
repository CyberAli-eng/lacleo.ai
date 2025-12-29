<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;

class FacetFilterHandler extends AbstractFilterHandler
{
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array
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

        if (! empty($search)) {
            $elastic->must([
                'bool' => [
                    'should' => [
                        ['prefix' => [$field => $search]],
                        ['prefix' => [$field . '.lowercase' => strtolower($search)]],
                        ['match_phrase_prefix' => [$field => $search]],
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

        $elastic->termsAggregation(
            'distinct_values',
            $aggField,
            [
                'size' => 1000,
                'order' => ['_key' => 'asc'],
            ]
        );

        $result = $elastic->paginate($page, $perPage);

        $values = collect($result['aggregations']['distinct_values']['buckets'] ?? [])
            ->map(fn ($bucket) => [
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
        $field = $fields[0]; // Facets usually apply to a single field

        $included = array_column(array_filter($values, fn ($v) => ! ($v['excluded'] ?? false) && isset($v['value'])), 'value');
        $excluded = array_column(array_filter($values, fn ($v) => ($v['excluded'] ?? false) && isset($v['value'])), 'value');
        
        $presence = null;
        foreach ($values as $v) {
            if (isset($v['presence'])) {
                $presence = $v['presence'];
                break;
            }
        }

        if ($presence === 'known') {
            $query->must(['exists' => ['field' => $field]]);
        } elseif ($presence === 'unknown') {
            $query->mustNot(['exists' => ['field' => $field]]);
        }

        if (! empty($included)) {
            $query->must(['terms' => [$field => $included]]);
        }

        if (! empty($excluded)) {
            $query->mustNot(['terms' => [$field => $excluded]]);
        }

        return $query;
    }
}
