<?php

namespace App\Elasticsearch;

use InvalidArgumentException;

class ElasticQueryBuilder
{
    protected array $query = [];

    protected array $boolClauses = [
        'must' => [],
        'should' => [],
        'filter' => [],
        'must_not' => [],
    ];

    protected array $boolParams = [];

    protected ?array $pagination = null;

    protected array $sort = [];

    protected array $select = [];

    protected ?array $highlight = null;

    protected ?array $aggregations = null;

    protected ?float $minimumScore = null;

    protected bool $trackTotalHits = true;

    protected ?string $index = null;

    protected ?array $knn = null;

    protected ?array $searchAfter = null;

    public function __construct(protected string $model)
    {
    }

    public function knn(array $config): self
    {
        $this->knn = $config;
        return $this;
    }

    public function searchAfter(array $values): self
    {
        $this->searchAfter = $values;
        return $this;
    }

    /**
     * Get the query for different operators
     */
    protected function getRangeQuery(string $operator, mixed $value): array
    {
        return match ($operator) {
            '>' => ['gt' => $value],
            '>=' => ['gte' => $value],
            '<' => ['lt' => $value],
            '<=' => ['lte' => $value]
        };
    }

    /**
     * Summary of addClause
     *
     * @param  mixed  $operator
     *
     * @throws \InvalidArgumentException
     */
    protected function addClause(string $type, array|string $field, mixed $value = null, ?string $operator = null): self
    {
        if (is_array($field)) {
            $this->boolClauses[$type][] = $field;

            return $this;
        }

        $clause = match ($operator) {
            null, '=' => ['term' => [$field => $value]],
            'like', 'match' => ['match' => [$field => $value]],
            'prefix' => ['prefix' => [$field => $value]],
            'wildcard' => ['wildcard' => [$field => $value]],
            'range' => ['range' => [$field => $value]],
            '>', '>=', '<', '<=' => ['range' => [$field => $this->getRangeQuery($operator, $value)]],
            default => throw new InvalidArgumentException("Unsupported operator: $operator")
        };

        $this->boolClauses[$type][] = $clause;

        return $this;
    }

    public function setBoolParam(string $param, mixed $value): self
    {
        $this->boolParams[$param] = $value;

        return $this;
    }

    public function addBoolQuery(array $query): self
    {
        $this->query = array_merge_recursive($this->query, $query);

        return $this;
    }

    /**
     * Add a must clause to the query
     */
    public function must(array|string $field, mixed $value = null, ?string $operator = null): self
    {
        return $this->addClause('must', $field, $value, $operator);
    }

    /**
     * Add a should clause to the query
     */
    public function should(array|string $field, mixed $value = null, ?string $operator = null): self
    {
        return $this->addClause('should', $field, $value, $operator);
    }

    /**
     * Add a filter clause to the query
     */
    public function filter(array|string $field, mixed $value = null): self
    {
        return $this->addClause('filter', $field, $value);
    }

    /**
     * Add a must_not clause to the query
     */
    public function mustNot(array|string $field, mixed $value = null, ?string $operator = null): self
    {
        return $this->addClause('must_not', $field, $value, $operator);
    }

    /**
     * Add a multi-match query
     */
    public function multiMatch(string $query, array $fields, array $options = []): self
    {
        $this->must([
            'multi_match' => array_merge([
                'query' => $query,
                'fields' => $fields,
                'type' => 'best_fields',
            ], $options)
        ]);

        return $this;
    }

    /**
     * Add a fuzzy match query
     */
    public function fuzzy(string $field, string $value, array $options = []): self
    {
        $this->must(['fuzzy' => [$field => array_merge(['value' => $value], $options)]]);

        return $this;
    }

    /**
     * Add highlighting to the query
     */
    public function highlight(array $fields, array $options = []): self
    {
        $this->highlight = array_merge([
            'fields' => array_fill_keys($fields, (object) []),
            'pre_tags' => ['<em>'],
            'post_tags' => ['</em>'],
            'fragment_size' => 150,
            'number_of_fragments' => 3,
        ], $options);

        return $this;
    }

    /**
     * Add aggregations to the query
     */
    public function aggregations(array $aggregations): self
    {
        $this->aggregations = $aggregations;

        return $this;
    }

    /**
     * Add a terms aggregation
     */
    public function termsAggregation(string $name, string $field, array $options = []): self
    {
        $this->aggregations[$name] = [
            'terms' => array_merge(
                ['field' => $field],
                array_filter($options, fn($key) => !in_array($key, ['terms']), ARRAY_FILTER_USE_KEY)
            ),
        ];

        return $this;
    }

    /**
     * Add sorting to the query
     */
    public function sort(string|array $field, string $direction = 'asc'): self
    {
        if (is_array($field)) {
            foreach ($field as $f => $dir) {
                $this->sort[] = [$f => ['order' => $dir]];
            }

            return $this;
        }
        $this->sort[] = [$field => ['order' => $direction]];

        return $this;
    }

    /**
     * Add source filtering (select fields)
     */
    public function select(array $fields): self
    {
        $this->select = $fields;

        return $this;
    }

    /**
     * Set minimum score threshold
     */
    public function minScore(float $score): self
    {
        $this->minimumScore = $score;

        return $this;
    }

    /**
     * Set whether to track total hits
     */
    public function trackTotalHits(bool $track = true): self
    {
        $this->trackTotalHits = $track;

        return $this;
    }

    /**
     * Paginate the results
     */
    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 1000));

        $this->pagination = [($page - 1) * $perPage, $perPage];
        $result = $this->get();

        return [
            'data' => array_map(fn($hit) => [
                '_id' => $hit['_id'],
                ...$hit['_source'],
                ...isset($hit['highlight']) ? ['highlights' => $hit['highlight']] : [],
                'raw' => $hit,
            ], $result['hits']['hits']),
            'total' => $result['hits']['total']['value'],
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($result['hits']['total']['value'] / $perPage),
            'aggregations' => $result['aggregations'] ?? [],
        ];
    }

    /**
     * Get the results
     */
    public function get(): ?array
    {
        $options = [];
        if ($this->index !== null) {
            $options['index'] = $this->index;
        }
        return $this->model::searchInElastic($this->buildQuery(), $options);
    }

    /**
     * Get the first result
     */
    public function first(): ?array
    {
        $this->pagination = [0, 1];
        $result = $this->get();

        return $result['hits']['hits'][0]['_source'] ?? null;
    }

    /**
     * Count the results
     */
    public function count(): int
    {
        $built = $this->buildQuery();
        $query = $built['query'] ?? ['match_all' => (object) []];
        $options = [];
        if ($this->index !== null) {
            $options['index'] = $this->index;
        }
        $result = $this->model::searchInElastic([
            'track_total_hits' => true,
            'size' => 0,
            'query' => $query,
        ], $options);

        return $result['hits']['total']['value'] ?? 0;
    }

    /**
     * Build the final query
     */
    protected function buildQuery(): array
    {
        $query = ['track_total_hits' => $this->trackTotalHits];

        $boolClauses = array_filter($this->boolClauses);
        if ($boolClauses) {
            $bool = $boolClauses;
            if (!empty($bool['should']) && empty($bool['must'])) {
                $bool['minimum_should_match'] = 1;
            }
            $query['query'] = ['bool' => array_merge($bool, $this->boolParams)];
        } else {
            $query['query'] = ['match_all' => (object) []];
        }

        if ($this->pagination) {
            [$from, $size] = $this->pagination;
            $query['from'] = $from;
            $query['size'] = $size;
        }

        // Inject filters into KNN for pre-filtering if they exist
        $knnStub = null;
        if ($this->knn) {
            $knnStub = $this->knn;
            // Map 'filter' clauses to KNN filter
            if (!empty($this->boolClauses['filter'])) {
                // Determine if we wrap in bool or pass array. KNN filter expects a Query.
                $filterQuery = count($this->boolClauses['filter']) === 1
                    ? $this->boolClauses['filter'][0]
                    : ['bool' => ['filter' => $this->boolClauses['filter']]];

                $knnStub['filter'] = $filterQuery;
            }
        }

        return array_merge($query, array_filter([
            'knn' => $knnStub,
            'sort' => $this->sort ?: null,
            '_source' => $this->select ?: null,
            'highlight' => $this->highlight,
            'aggs' => $this->aggregations,
            'min_score' => $this->minimumScore,
            'search_after' => $this->searchAfter,
        ]));
    }

    public function toArray(): array
    {
        return $this->buildQuery();
    }

    public function index(string $index): self
    {
        $this->index = $index;
        return $this;
    }
}
