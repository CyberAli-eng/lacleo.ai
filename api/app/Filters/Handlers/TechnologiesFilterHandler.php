<?php

namespace App\Filters\Handlers;

use App\Elasticsearch\ElasticClient;
use App\Elasticsearch\ElasticQueryBuilder;

class TechnologiesFilterHandler extends AbstractFilterHandler
{
    /**
     * Get possible values for this filter from stored company_technologies field
     * Note: Since company_technologies is not indexed, we retrieve it from all documents and extract unique values
     */
    public function getValues(?string $search = null, int $page = 1, int $perPage = 10): array
    {
        try {
            // Get Elasticsearch client from service container
            $elasticClient = app(ElasticClient::class);
            $indexAlias = 'stage_lacleo_company_stats';

            // Fetch a large batch of documents to extract unique technologies
            $result = $elasticClient->search([
                'index' => $indexAlias,
                'body' => [
                    'size' => 10000,
                    'query' => ['match_all' => new \stdClass()],
                    '_source' => ['company_technologies'],
                ],
            ]);

            $uniqueTechnologies = [];

            // Parse comma-separated technologies from each document
            foreach ($result['hits']['hits'] as $hit) {
                $source = $hit['_source'] ?? [];
                $techString = $source['company_technologies'] ?? '';

                if (!empty($techString)) {
                    // Split by comma and clean up
                    $techs = array_map('trim', explode(',', $techString));
                    foreach ($techs as $tech) {
                        if (!empty($tech)) {
                            $uniqueTechnologies[$tech] = ($uniqueTechnologies[$tech] ?? 0) + 1;
                        }
                    }
                }
            }

            // Apply search filter if provided
            if (!empty($search)) {
                $searchLower = strtolower($search);
                $uniqueTechnologies = array_filter(
                    $uniqueTechnologies,
                    fn($tech) => stripos($tech, $searchLower) !== false,
                    ARRAY_FILTER_USE_KEY
                );
            }

            // Sort by count descending, then alphabetically
            arsort($uniqueTechnologies);

            // Convert to array format
            $values = array_map(
                fn($tech, $count) => [
                    'id' => $tech,
                    'name' => $tech,
                ],
                array_keys($uniqueTechnologies),
                $uniqueTechnologies
            );

            return $this->paginateResults($values, $page, $perPage);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting technologies values', [
                'error' => $e->getMessage(),
            ]);
            return $this->emptyPaginatedResponse($page, $perPage);
        }
    }

    /**
     * Apply the technologies filter via SearchService's filter_dsl
     * Note: This method is not actually used since SearchController uses filter_dsl directly
     */
    public function apply(ElasticQueryBuilder $query, array $values): ElasticQueryBuilder
    {
        // This handler doesn't apply filters via ElasticQueryBuilder
        // Filtering happens at the application level in SearchService->formatResults()
        return $query;
    }

    /**
     * Validate that the provided values are valid technology names
     */
    public function validateValues(array $values): bool
    {
        // Basic validation: all values should be non-empty strings
        foreach ($values as $value) {
            if (empty($value['id']) || empty($value['value'])) {
                return false;
            }
        }
        return true;
    }
}
