<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;

class RangeFilterHandler extends AbstractFilterHandler
{
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array
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

        $should = [];

        foreach ($values as $valItem) {
            $val = $valItem['value'];
            // Handle different range formats
            // 1. Array with min/max
            if (is_array($val)) {
                $range = [];
                // Check explicitly for null or empty string to allow 0
                if (isset($val['min']) && $val['min'] !== '' && $val['min'] !== null) {
                    $range['gte'] = $val['min'];
                }
                if (isset($val['max']) && $val['max'] !== '' && $val['max'] !== null) {
                    $range['lte'] = $val['max'];
                }
                
                if (!empty($range)) {
                    $should[] = ['range' => [$field => $range]];
                }
            } 
            // 2. String "10-50", "100+", ">100"
            elseif (is_string($val)) {
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
