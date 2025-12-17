<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;
use DateTime;
use Exception;
use InvalidArgumentException;

class DirectInputFilterHandler extends AbstractFilterHandler
{
    /**
     * Direct input filters don't have predefined values
     */
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array
    {
        return $this->emptyPaginatedResponse($page, $perPage);
    }

    /**
     * Apply the direct input filter to the query
     */
    public function apply(ElasticQueryBuilder $query, array $values): ElasticQueryBuilder
    {
        $field = $this->filter->elasticsearch_field;

        foreach ($values as $value) {
            $clause = match ($this->filter->value_type) {
                'string' => [
                    'match' => [
                        $field => [
                            'query' => $value['value'],
                            'operator' => 'and',
                        ],
                    ],
                ],
                'number' => (function () use ($field, $value) {
                    $raw = $value['value'] ?? $value;
                    if (is_string($raw)) {
                        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $raw, $m)) {
                            return ['range' => [$field => ['gte' => (int) $m[1], 'lte' => (int) $m[2]]]];
                        }
                        if (preg_match('/^>=\s*(\d+)$/', $raw, $m)) {
                            return ['range' => [$field => ['gte' => (int) $m[1]]]];
                        }
                        if (preg_match('/^<=\s*(\d+)$/', $raw, $m)) {
                            return ['range' => [$field => ['lte' => (int) $m[1]]]];
                        }
                        if (preg_match('/^\d+$/', $raw)) {
                            return ['term' => [$field => (int) $raw]];
                        }
                    }
                    if (is_numeric($raw)) {
                        return ['term' => [$field => (int) $raw]];
                    }

                    return ['exists' => ['field' => $field]]; // fallback
                })(),
                'boolean' => ['term' => [$field => $value]],
                'date' => ['range' => [$field => ['gte' => $value]]],
                default => throw new InvalidArgumentException("Unsupported value type: {$this->filter->value_type}")
            };

            if ($value['excluded']) {
                $query->mustNot($clause);
            } else {
                $query->must($clause);
            }
        }

        return $query;
    }

    /**
     * Validate the input values based on filter type and settings
     */
    public function validateValues(array $values): bool
    {
        if (empty($values)) {
            return false;
        }

        $settings = $this->filter->settings ?? [];

        foreach ($values as $value) {
            $isValid = match ($this->filter->value_type) {
                'string' => $this->validateString($value['value'], $settings),
                'number' => $this->validateNumber($value['value'], $settings),
                'boolean' => is_bool($value['value']),
                'date' => $this->validateDate($value['value'], $settings),
                default => false
            };

            if (! $isValid) {
                return false;
            }
        }

        return true;
    }

    protected function validateString(string $value, array $settings): bool
    {
        $length = mb_strlen($value);
        $minLength = $settings['validation']['min_length'] ?? 0;
        $maxLength = $settings['validation']['max_length'] ?? PHP_INT_MAX;
        $pattern = $settings['validation']['pattern'] ?? null;

        return $length >= $minLength &&
               $length <= $maxLength &&
               (! $pattern || preg_match($pattern, $value));
    }

    protected function validateNumber($value, array $settings): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        $min = $settings['validation']['min'] ?? PHP_FLOAT_MIN;
        $max = $settings['validation']['max'] ?? PHP_FLOAT_MAX;

        return $value >= $min && $value <= $max;
    }

    protected function validateDate($value, array $settings): bool
    {
        try {
            $date = new DateTime($value);
            $minDate = isset($settings['validation']['min_date']) ? new DateTime($settings['validation']['min_date']) : null;
            $maxDate = isset($settings['validation']['max_date']) ? new DateTime($settings['validation']['max_date']) : null;

            return (! $minDate || $date >= $minDate) &&
                   (! $maxDate || $date <= $maxDate);
        } catch (Exception) {
            return false;
        }
    }
}
