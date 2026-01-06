<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;
use App\Models\Filter;

class TextFilterHandler extends AbstractFilterHandler
{
    /**
     * Get possible values for this filter (suggestions)
     */
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10, array $context = [], ?string $searchType = null): array
    {
        // For text filters, we typically use the ElasticsearchFilterHandler logic for suggestions
        $handler = new ElasticsearchFilterHandler($this->filter, $this->manager);
        return $handler->getValues($search, $page, $perPage, $context, $searchType);
    }

    public function validateValues(array $values): bool
    {
        return !empty($values['include']) || !empty($values['exclude']);
    }

    /**
     * Apply the text filter to the query
     */
    public function apply(ElasticQueryBuilder $query, array $values, string $context = 'company'): ElasticQueryBuilder
    {
        $fields = $this->filter->settings['fields'][$context] ?? [];
        if (empty($fields)) {
            return $query;
        }
        $field = $fields[0];

        $include = $values['include'] ?? [];
        $exclude = $values['exclude'] ?? [];
        $operator = $values['operator'] ?? 'or';

        if (!empty($include)) {
            $this->applyInclude($query, $field, $include, $operator);
        }

        if (!empty($exclude)) {
            $this->applyExclude($query, $field, $exclude);
        }

        return $query;
    }

    protected function applyInclude(ElasticQueryBuilder $query, string $field, array $values, string $operator): void
    {
        if ($operator === 'and') {
            foreach ($values as $v) {
                $query->filter($this->wrapIfNested($field, $this->buildClause($field, $v)));
            }
        } else {
            $should = array_map(fn($v) => $this->buildClause($field, $v), $values);
            $query->filter($this->wrapIfNested($field, [
                'bool' => [
                    'should' => $should,
                    'minimum_should_match' => 1,
                ],
            ]));
        }
    }

    protected function applyExclude(ElasticQueryBuilder $query, string $field, array $values): void
    {
        foreach ($values as $v) {
            $query->mustNot($this->wrapIfNested($field, $this->buildClause($field, $v)));
        }
    }

    /**
     * Build the appropriate clause for a value, handling absolute search (double quotes)
     */
    protected function buildClause(string $field, string $value): array
    {
        $trimmed = trim($value);

        // Check for absolute search (wrapped in double quotes)
        if (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"') && strlen($trimmed) > 2) {
            $innerValue = substr($trimmed, 1, -1);
            return ['match_phrase' => [$field => $innerValue]];
        }

        // Default to standard match
        return ['match' => [$field => $value]];
    }
}