<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;

class BooleanFilterHandler extends AbstractFilterHandler
{
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array
    {
        return $this->paginateResults([
            ['id' => 'true', 'name' => 'Yes'],
            ['id' => 'false', 'name' => 'No'],
        ], $page, $perPage);
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

        foreach ($values as $valItem) {
            $val = $valItem['value'];
            $isTrue = $val === true || $val === 'true' || $val === 1 || $val === '1';
            
            if ($isTrue) {
                $query->filter(['exists' => ['field' => $field]]);
            } else {
                $query->mustNot(['exists' => ['field' => $field]]);
            }
        }

        return $query;
    }
}
