<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;

class DirectFilterHandler extends AbstractFilterHandler
{
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10, array $context = [], ?string $searchType = null): array
    {
        return $this->emptyPaginatedResponse($page, $perPage);
    }

    public function validateValues(array $values): bool
    {
        return true;
    }

    public function apply(ElasticQueryBuilder $query, array $values, string $context = 'company'): ElasticQueryBuilder
    {
        // Direct filters are often exact matches or prefix matches on specific fields
        $fields = $this->filter->settings['fields'][$context] ?? [];
        if (empty($fields)) {
            return $query;
        }
        $field = $fields[0];

        $included = $values['include'] ?? [];
        $excluded = $values['exclude'] ?? [];

        if (!empty($included)) {
            $should = [];
            foreach ($included as $val) {
                // Use term for direct exact match, or match_phrase_prefix for partial if needed
                // Schema says "direct" usually means specific lookups. 
                // Let's use match query for direct text fields.
                $should[] = ['match' => [$field => $val]];
            }
            $query->must(['bool' => ['should' => $should, 'minimum_should_match' => 1]]);
        }

        if (!empty($excluded)) {
            foreach ($excluded as $val) {
                $query->mustNot(['match' => [$field => $val]]);
            }
        }

        return $query;
    }
}
