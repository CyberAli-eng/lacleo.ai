<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;
use App\Models\FilterValue;

class PredefinedFilterHandler extends AbstractFilterHandler
{
    /**
     * Get possible values for this filter
     */
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array
    {
        $query = $this->filter->filterValues()->active();

        // Apply search if filter is searchable
        if ($this->filter->is_searchable && $search) {
            $query->where('display_value', 'like', "%{$search}%");
        }

        $values = $query->ordered()->get()
            ->map(fn (FilterValue $value) => [
                'id' => $value->value_id,
                'name' => $value->display_value,
                'metadata' => $value->metadata,
            ])
            ->toArray();

        return $this->paginateResults($values, $page, $perPage);
    }

    /**
     * Apply the predefined filter to the query
     */
    public function apply(ElasticQueryBuilder $query, array $values): ElasticQueryBuilder
    {

        $valueIds = array_map(fn ($value) => $value['id'], $values);
        $filterValues = $this->filter->filterValues()->active()->whereIn('value_id', $valueIds)->get();

        if ($filterValues->isEmpty()) {
            return $query;
        }

        $included = array_filter($values, fn ($v) => ! $v['excluded']);
        $excluded = array_filter($values, fn ($v) => $v['excluded']);

        if (! empty($included)) {
            $this->applyFilter($query, array_column($included, 'id'), 'must');
        }
        if (! empty($excluded)) {
            $this->applyFilter($query, array_column($excluded, 'id'), 'mustNot');
        }

        return $query;
    }

    protected function applyFilter(ElasticQueryBuilder $query, array $values, string $type): void
    {
        if (empty($values)) {
            return;
        }

        $filterValues = $this->filter->filterValues()
            ->active()
            ->whereIn('value_id', $values)
            ->get();

        if ($filterValues->isEmpty()) {
            return;
        }

        $field = $this->filter->elasticsearch_field;
        $shouldClauses = $this->buildClauses($filterValues, $field);

        if (! empty($shouldClauses)) {
            $query->$type([
                'bool' => [
                    'should' => $shouldClauses,
                    'minimum_should_match' => 1,
                ],
            ]);
        }
    }

    protected function buildClauses($filterValues, string $field): array
    {
        return $filterValues->map(function ($filterValue) use ($field) {
            if (! empty($filterValue->metadata['range'])) {
                return $this->buildRangeClause($field, $filterValue->metadata['range']);
            }

            return ['term' => [$field => $filterValue->display_value]];
        })->all();
    }

    protected function buildRangeClause(string $field, array $range): array
    {
        $conditions = [];
        if (isset($range['min'])) {
            $conditions['gte'] = $range['min'];
        }
        if (isset($range['max'])) {
            $conditions['lte'] = $range['max'];
        }

        return ['range' => [$field => $conditions]];
    }

    /**
     * Validate the input values
     */
    public function validateValues(array $values): bool
    {
        $valueIds = array_map(fn ($value) => $value['id'], $values);

        if (empty($valueIds) || ($this->filter->input_type === 'select' && count($valueIds) > 1)) {
            return false;
        }

        return $this->filter->filterValues()->whereIn('value_id', $valueIds)->exists();
    }
}
