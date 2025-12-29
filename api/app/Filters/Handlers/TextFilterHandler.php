<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;
use Illuminate\Support\Arr;

class TextFilterHandler extends AbstractFilterHandler
{
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array
    {
        // Text filters usually don't support aggregations unless they are also keyword fields
        // or have a suggestion implementation.
        // For now, return empty or implement suggestion if 'suggest_fields' is present.
        return $this->emptyPaginatedResponse($page, $perPage);
    }

    public function validateValues(array $values): bool
    {
        return true; // Basic validation
    }

    public function apply(ElasticQueryBuilder $query, array $values, string $context = 'company'): ElasticQueryBuilder
    {
        // Get fields for current context
        $fields = $this->filter->settings['fields'][$context] ?? [];
        if (empty($fields)) {
            return $query;
        }

        $included = array_column(array_filter($values, fn ($v) => ! ($v['excluded'] ?? false)), 'value');
        $excluded = array_column(array_filter($values, fn ($v) => ($v['excluded'] ?? false)), 'value');

        if (! empty($included)) {
            $should = [];
            foreach ($included as $val) {
                $should[] = [
                    'multi_match' => [
                        'query' => $val,
                        'fields' => $fields,
                        'type' => 'best_fields', // or phrase_prefix depending on requirement
                        'operator' => 'and',
                    ],
                ];
            }
            $query->must(['bool' => ['should' => $should, 'minimum_should_match' => 1]]);
        }

        if (! empty($excluded)) {
            foreach ($excluded as $val) {
                $query->mustNot([
                    'multi_match' => [
                        'query' => $val,
                        'fields' => $fields,
                        'type' => 'best_fields',
                        'operator' => 'and',
                    ],
                ]);
            }
        }

        return $query;
    }
}
