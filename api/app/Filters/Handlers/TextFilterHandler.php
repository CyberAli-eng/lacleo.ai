<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticQueryBuilder;
use Illuminate\Support\Arr;
use App\Services\SearchTermNormalizer;
use App\Services\FilterFieldResolver;

class TextFilterHandler extends AbstractFilterHandler
{
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10, array $context = []): array
    {
        if (empty($search)) {
            return $this->emptyPaginatedResponse($page, $perPage);
        }

        // 1. Normalize (USA -> United States)
        $normalizedSearch = SearchTermNormalizer::normalize($search);

        $targetModel = $this->filter->getTargetModelOrFail();
        $elastic = $targetModel::elastic();

        $entityContext = ($targetModel === \App\Models\Company::class) ? 'company' : 'contact';
        $suggestFields = $this->filter->settings['fields'][$entityContext] ?? [];

        if (empty($suggestFields)) {
            // Fallback to resolver if registry fails
            $resolvedField = FilterFieldResolver::resolve($this->filter->filter_id);
            $suggestFields = [$resolvedField];
        }

        if (empty($suggestFields)) {
            return $this->emptyPaginatedResponse($page, $perPage);
        }

        if (config('app.debug')) {
            \Log::debug('TextFilterHandler::getValues', [
                'filter_id' => $this->filter->filter_id,
                'resolved_field' => $suggestFields[0] ?? 'unknown',
                'original_search' => $search,
                'normalized_search' => $normalizedSearch,
            ]);
        }

        $should = [];

        foreach ($suggestFields as $field) {
            $baseField = str_replace(['.sort', '.keyword'], '', $field);

            // Use the filter type for robust keyword detection
            $isKeyword = $this->filter->type === 'keyword';

            if ($isKeyword) {
                // KEYWORD STRATEGY
                // 1. Wildcard (Contains) - Case Insensitive
                $should[] = $this->wrapIfNested($baseField, ['wildcard' => [$baseField => ['value' => '*' . $normalizedSearch . '*', 'boost' => 5, 'case_insensitive' => true]]]);

                // 2. Exact Term (for shortcuts like "USA" normalized to "United States")
                $should[] = $this->wrapIfNested($baseField, ['term' => [$baseField => ['value' => $normalizedSearch, 'boost' => 10, 'case_insensitive' => true]]]);

                // 3. Prefix (if supported)
                $should[] = $this->wrapIfNested($baseField, ['prefix' => [$baseField => ['value' => $normalizedSearch, 'boost' => 4, 'case_insensitive' => true]]]);
            } else {
                // TEXT STRATEGY
                // 1. Phrase Prefix
                $should[] = $this->wrapIfNested($baseField, ['match_phrase_prefix' => [$baseField => ['query' => $normalizedSearch, 'boost' => 2]]]);

                // 2. Prefix Subfield
                $should[] = $this->wrapIfNested($baseField, ['match_phrase_prefix' => [$baseField . '.prefix' => ['query' => $normalizedSearch, 'boost' => 5]]]);
            }
        }

        $elastic->must([
            'bool' => [
                'should' => $should,
                'minimum_should_match' => 1
            ]
        ]);

        if (config('app.debug')) {
            \Log::debug('TextFilterHandler::getValues Query Body', [
                'filter_id' => $this->filter->filter_id,
                'query' => $elastic->toArray()
            ]);
        }

        // Add aggregation for counts if context is present or for better suggestions
        $baseField = str_replace(['.sort', '.keyword'], '', $suggestFields[0]);
        $aggField = $this->filter->type === 'keyword' ? $baseField : $baseField . '.keyword';

        // Handle canonical DSL
        $contactContext = $context['contact'] ?? [];
        $companyContext = $context['company'] ?? [];
        if (empty($contactContext) && empty($companyContext) && !empty($context)) {
            if ($context === 'contact')
                $contactContext = $context;
            elseif ($context === 'company')
                $companyContext = $context;
        }

        // Exclude current filter
        unset($contactContext[$this->filter->filter_id]);
        unset($companyContext[$this->filter->filter_id]);

        if (!empty($contactContext) && $this->manager) {
            $this->manager->applyFilters($elastic, $contactContext, 'contact');
        }
        if (!empty($companyContext) && $this->manager) {
            $this->manager->applyFilters($elastic, $companyContext, 'company');
        }

        $elastic->size(0); // Only care about aggs for counts
        $elastic->aggregations([
            'suggestions' => [
                'terms' => [
                    'field' => $aggField,
                    'size' => 50, // Get more for better filtering/processing
                    'include' => '.*' . preg_quote($normalizedSearch) . '.*'
                ]
            ]
        ]);

        $aggResult = $elastic->search();
        $buckets = $aggResult['aggregations']['suggestions']['buckets'] ?? [];

        $values = [];
        $seen = [];

        foreach ($buckets as $bucket) {
            $val = $bucket['key'];
            $count = $bucket['doc_count'];

            if ($val && !in_array(strtolower($val), $seen)) {
                $values[] = ['id' => $val, 'name' => $val, 'count' => $count];
                $seen[] = strtolower($val);
            }
            if (count($values) >= $perPage)
                break;
        }

        // Fallback to hits if aggs empty or insufficient (though aggs with include should be fine)
        if (empty($values)) {
            // Re-run original search to get hits if aggs failed
            $elastic = $targetModel::elastic();
            $elastic->must(['bool' => ['should' => $should, 'minimum_should_match' => 1]]);
            $result = $elastic->paginate($page, $perPage);
            $hits = $result['data'] ?? [];
            foreach ($hits as $hit) {
                foreach ($suggestFields as $field) {
                    $baseField = str_replace(['.sort', '.keyword'], '', $field);
                    $val = Arr::get($hit, $baseField);
                    $this->processValue($values, $seen, $val, $perPage, $normalizedSearch);
                    if (count($values) >= $perPage)
                        break 2;
                }
            }
        }

        // Inject Region Matches (Virtual Suggestions)
        // If the user searches "Eur", we want "Europe" to appear even if not in DB
        $regions = SearchTermNormalizer::getAllRegions(); // We need to add this method or access property
        foreach ($regions as $region => $countries) {
            if (stripos($region, $search) !== false && !in_array(strtolower($region), $seen)) {
                array_unshift($values, ['id' => ucfirst($region), 'name' => ucfirst($region), 'count' => -1]); // -1 count to indicate special
                $seen[] = strtolower($region);
            }
        }
        // Slice again just in case injection pushed over limit
        $values = array_slice($values, 0, $perPage);

        return $this->paginateResults($values, $page, $perPage);
    }

    private function processValue(array &$values, array &$seen, mixed $val, int $perPage, ?string $search): void
    {
        if (!$val)
            return;

        if (is_array($val)) {
            foreach ($val as $v) {
                // If it's a nested object, we need to find the actual leaf value
                // For 'emails' field, we get the array of objects.
                // However, Arr::get($hit, 'emails.email') should have returned null or something else.
                // Let's make this more robust.
                if (is_array($v)) {
                    foreach ($v as $subVal) {
                        $this->processValue($values, $seen, $subVal, $perPage, $search);
                    }
                } else {
                    $this->processOne($values, $seen, $v, $perPage, $search);
                }
            }
            return;
        }

        $this->processOne($values, $seen, $val, $perPage, $search);
    }

    private function processOne(array &$values, array &$seen, mixed $val, int $perPage, ?string $search): void
    {
        if (!$val || !is_string($val))
            return;

        $checkMatch = function ($term) use ($search) {
            if (empty($search))
                return true;
            return stripos($term, $search) !== false;
        };

        // Comma-separated logic (e.g. "React, Vue")
        if (str_contains($val, ',')) {
            $parts = array_map('trim', explode(',', $val));
            foreach ($parts as $part) {
                if ($part && !in_array(strtolower($part), $seen) && $checkMatch($part)) {
                    $values[] = ['id' => $part, 'name' => $part, 'count' => 1];
                    $seen[] = strtolower($part);
                    if (count($values) >= $perPage)
                        return;
                }
            }
        } else {
            if (!in_array(strtolower($val), $seen) && $checkMatch($val)) {
                $values[] = ['id' => $val, 'name' => $val, 'count' => 1];
                $seen[] = strtolower($val);
            }
        }
    }

    /**
     * Expand common job title abbreviations to their full forms
     * Returns array of terms to match (original + expanded)
     */
    private function expandJobTitleAbbreviation(string $term): array
    {
        $abbreviations = [
            'cto' => ['CTO', 'Chief Technical Officer', 'Chief Technology Officer'],
            'ceo' => ['CEO', 'Chief Executive Officer'],
            'cfo' => ['CFO', 'Chief Financial Officer'],
            'coo' => ['COO', 'Chief Operating Officer'],
            'cmo' => ['CMO', 'Chief Marketing Officer'],
            'cio' => ['CIO', 'Chief Information Officer'],
            'cpo' => ['CPO', 'Chief Product Officer'],
            'cso' => ['CSO', 'Chief Security Officer', 'Chief Strategy Officer'],
            'vp' => ['VP', 'Vice President'],
            'svp' => ['SVP', 'Senior Vice President'],
            'evp' => ['EVP', 'Executive Vice President'],
            'founder' => ['Founder', 'Co-Founder', 'Cofounder'],
        ];

        $lowerTerm = strtolower($term);

        if (isset($abbreviations[$lowerTerm])) {
            return $abbreviations[$lowerTerm];
        }

        return [$term];
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

        $included = $values['include'] ?? [];
        $excluded = $values['exclude'] ?? [];
        $operator = $values['operator'] ?? 'and';

        if (!empty($included)) {
            $clauses = [];
            foreach ($included as $val) {
                // Detect if value is wrapped in quotes for exact matching
                $isExactMatch = (preg_match('/^["\'](.+)["\']$/', $val, $matches));
                $searchTerm = $isExactMatch ? $matches[1] : $val;

                // 1. Normalize
                $normalizedVal = SearchTermNormalizer::normalize($searchTerm);

                // 2. Expand job title abbreviations (CTO -> Chief Technical Officer)
                $expandedTerms = $this->expandJobTitleAbbreviation($normalizedVal);

                // 3. Expand Region (Europe -> Countries)
                $expandedCountries = SearchTermNormalizer::expandRegion($normalizedVal);
                $termsToMatch = $expandedCountries ?: $expandedTerms;

                foreach ($termsToMatch as $matchTerm) {
                    $termClauses = [];
                    foreach ($fields as $field) {
                        $baseField = str_replace(['.sort', '.keyword'], '', $field);
                        $isKeyword = $this->filter->type === 'keyword';

                        if ($isKeyword) {
                            // KEYWORD MATCH
                            if ($isExactMatch) {
                                // Exact match only - use .keyword for precision
                                $termClauses[] = $this->wrapIfNested($baseField, ['term' => [$baseField . '.keyword' => ['value' => $matchTerm, 'boost' => 100, 'case_insensitive' => true]]]);
                            } else {
                                // Fuzzy match
                                $termClauses[] = $this->wrapIfNested($baseField, ['term' => [$baseField => ['value' => $matchTerm, 'boost' => 50, 'case_insensitive' => true]]]);
                                $termClauses[] = $this->wrapIfNested($baseField, ['wildcard' => [$baseField => ['value' => '*' . $matchTerm . '*', 'boost' => 5, 'case_insensitive' => true]]]);
                            }
                        } else {
                            // TEXT MATCH
                            if ($isExactMatch) {
                                // Exact match only - use .keyword field
                                $termClauses[] = $this->wrapIfNested($baseField, ['term' => [$baseField . '.keyword' => ['value' => $matchTerm, 'boost' => 200, 'case_insensitive' => true]]]);
                            } else {
                                // Fuzzy match - use all strategies
                                $termClauses[] = $this->wrapIfNested($baseField, ['term' => [$baseField . '.keyword' => ['value' => $matchTerm, 'boost' => 100, 'case_insensitive' => true]]]);
                                $termClauses[] = $this->wrapIfNested($baseField, ['match_phrase' => [$baseField => ['query' => $matchTerm, 'boost' => 20]]]);
                                $termClauses[] = $this->wrapIfNested($baseField, [
                                    'match' => [
                                        $baseField => [
                                            'query' => $matchTerm,
                                            'operator' => 'and',
                                            'boost' => 5
                                        ]
                                    ]
                                ]);
                            }
                        }
                    }
                    $clauses[] = ['bool' => ['should' => $termClauses, 'minimum_should_match' => 1]];
                }
            }

            if ($operator === 'or') {
                $query->should(['bool' => ['should' => $clauses, 'minimum_should_match' => 1]]);
            } else {
                $query->must(['bool' => ['should' => $clauses, 'minimum_should_match' => 1]]);
            }
        }

        if (!empty($excluded)) {
            foreach ($excluded as $val) {
                $normalizedVal = SearchTermNormalizer::normalize($val);
                $termClauses = [];
                foreach ($fields as $field) {
                    $baseField = str_replace(['.sort', '.keyword'], '', $field);
                    $termClauses[] = $this->wrapIfNested($baseField, ['wildcard' => [$baseField => ['value' => '*' . $normalizedVal . '*', 'case_insensitive' => true]]]);
                    $termClauses[] = $this->wrapIfNested($baseField, ['match' => [$baseField => ['query' => $normalizedVal]]]);
                }
                $query->mustNot(['bool' => ['should' => $termClauses, 'minimum_should_match' => 1]]);
            }
        }

        return $query;
    }
}
