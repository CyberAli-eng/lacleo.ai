<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;

class RangeFilterHandler extends AbstractFilterHandler
{
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10, array $context = []): array
    {
        // Ranges usually don't have listable values unless predefined.
        return $this->emptyPaginatedResponse($page, $perPage);
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

        $range = $values['range'] ?? null;

        // Standard range object {min, max} from FilterManager
        if (is_array($range)) {
            $params = [];
            if (isset($range['min']) && $range['min'] !== null) {
                $params['gte'] = $range['min'];
            }
            if (isset($range['max']) && $range['max'] !== null) {
                $params['lte'] = $range['max'];
            }

            if (!empty($params)) {
                $query->filter(['range' => [$field => $params]]);
            }
        }

        // Support for list-based ranges (e.g. from predefined dropdowns)
        // These might come in via 'include' if logic adapts? 
        // For now, based on normalized struct, strict ranges are in 'range'.
        // If predefined ranges passed as strings (e.g. "1-10") via 'include', handle them:

        $included = $values['include'] ?? [];
        $should = [];

        foreach ($included as $val) {
            if (is_string($val)) {
                if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $val, $m)) {
                    $should[] = ['range' => [$field => ['gte' => (int) $m[1], 'lte' => (int) $m[2]]]];
                } elseif (preg_match('/^(\d+)\+$/', $val, $m) || preg_match('/^>=\s*(\d+)$/', $val, $m)) {
                    $should[] = ['range' => [$field => ['gte' => (int) $m[1]]]];
                } elseif (preg_match('/^<=\s*(\d+)$/', $val, $m)) {
                    $should[] = ['range' => [$field => ['lte' => (int) $m[1]]]];
                }
            }
        }

        if (!empty($should)) {
            $query->must(['bool' => ['should' => $should, 'minimum_should_match' => 1]]);
        }

        return $query;
    }
}
