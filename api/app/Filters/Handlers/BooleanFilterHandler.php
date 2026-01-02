<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;

class BooleanFilterHandler extends AbstractFilterHandler
{
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10, array $context = []): array
    {
        return $this->paginateResults([
            ['id' => '1', 'name' => 'Yes'],
            ['id' => '0', 'name' => 'No'],
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

        // Boolean filters usually come as 'include' values like ['true'], ['false'], ['has_funding_has'], etc.
        $included = $values['include'] ?? [];
        $hasFundingCheck = $this->filter->settings['filtering']['has_funding_check'] ?? false;

        foreach ($included as $val) {
            $isTrue = $val === true || $val === 'true' || $val === 1 || $val === '1' || $val === 'Has' || (is_string($val) && str_ends_with(strtolower($val), '_has'));
            $isFalse = $val === false || $val === 'false' || $val === 0 || $val === '0' || $val === 'Does not have' || (is_string($val) && str_ends_with(strtolower($val), '_not'));

            if ($isTrue) {
                if ($hasFundingCheck) {
                    // For funding, 'exists' isn't enough because many have 0. We want > 0.
                    $query->filter(['range' => [$field => ['gt' => 0]]]);
                } else {
                    $query->filter($this->wrapIfNested($field, ['exists' => ['field' => $field]]));
                }
            } elseif ($isFalse) {
                if ($hasFundingCheck) {
                    $query->filter([
                        'bool' => [
                            'should' => [
                                ['range' => [$field => ['lte' => 0]]],
                                ['bool' => ['must_not' => ['exists' => ['field' => $field]]]]
                            ]
                        ]
                    ]);
                } else {
                    $query->mustNot($this->wrapIfNested($field, ['exists' => ['field' => $field]]));
                }
            }
        }

        return $query;
    }
}
