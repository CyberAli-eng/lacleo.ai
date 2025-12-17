<?php

namespace App\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ElasticClient
{
    protected Client $client;

    protected array $config;

    protected array $defaultHeaders = [
        'Accept' => 'application/vnd.elasticsearch+json; compatible-with=8',
        'Content-Type' => 'application/vnd.elasticsearch+json; compatible-with=8',
    ];

    /**
     * Create a new ElasticClient instance.
     *
     * @throws Exception If elasticsearch configuration is invalid
     */
    public function __construct()
    {
        $this->config = config('elasticsearch');
        $this->validateConfig();
        $this->initializeClient();
    }

    /**
     * Validate elasticsearch configuration
     *
     * @throws InvalidArgumentException
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['hosts'])) {
            throw new InvalidArgumentException('Elasticsearch hosts configuration is missing');
        }
    }

    /**
     * Initialize Elasticsearch client
     */
    protected function initializeClient(): void
    {
        $builder = ClientBuilder::create()->setHosts($this->config['hosts']);

        // Configure SSL
        if ($this->config['ssl']['verify'] ?? false) {
            $builder->setSSLVerification($this->config['ssl']['cafile']);
        } else {
            $builder->setSSLVerification(false);
        }

        // Configure authentication
        $apiKey = $this->config['auth']['api_key'] ?? null;
        $apiKeySecret = $this->config['auth']['api_key_secret'] ?? null;
        if (! empty($apiKey) && ! empty($apiKeySecret)) {
            $builder->setApiKey(base64_encode($apiKey.':'.$apiKeySecret));
        } elseif (! empty($apiKey)) {
            $builder->setApiKey($apiKey);
        } elseif (! empty($this->config['auth']['username'])) {
            // Fallback to basic auth if configured
            $builder->setBasicAuthentication(
                $this->config['auth']['username'],
                $this->config['auth']['password'] ?? ''
            );
        }

        // Configure retries
        if (! empty($this->config['retries'])) {
            $builder->setRetries($this->config['retries']);
        }

        $this->client = $builder->build();

        // Safety: block destructive operations in non-local envs unless explicitly allowed
        try {
            $env = config('app.env');
            $allowDestructive = filter_var(env('ELASTIC_ALLOW_DESTRUCTIVE', false), FILTER_VALIDATE_BOOLEAN);
            $contact = env('ELASTICSEARCH_CONTACT_INDEX');
            $company = env('ELASTICSEARCH_COMPANY_INDEX');
            $contactStats = env('ELASTICSEARCH_CONTACT_STATS_INDEX') ?: ($contact ? $contact.'_stats' : null);
            $companyStats = env('ELASTICSEARCH_COMPANY_STATS_INDEX') ?: ($company ? $company.'_stats' : null);

            // Ensure aliases exist on boot
            foreach (array_filter([$contact, $company, $contactStats, $companyStats]) as $alias) {
                if (! $alias) {
                    continue;
                }
                $settings = [];
                $mapping = [];
                if ($alias === $contact) {
                    $m = new \App\Models\Contact;
                    $settings = $m->elasticSettings();
                    $mapping = [
                        'dynamic' => $m->getDynamicMapSetting(),
                        'properties' => array_merge($m->getMappingProperties(), $m->getAddtionalMappingProperties()),
                        'dynamic_templates' => $m->getDynamicTemplates(),
                    ];
                } elseif ($alias === $company) {
                    $m = new \App\Models\Company;
                    $settings = $m->elasticSettings();
                    $mapping = [
                        'dynamic' => $m->getDynamicMapSetting(),
                        'properties' => array_merge($m->getMappingProperties(), $m->getAddtionalMappingProperties()),
                        'dynamic_templates' => $m->getDynamicTemplates(),
                    ];
                }
                $this->ensureAlias($alias, $settings, $mapping);
            }

            // Apply delete-blocks to all indices under aliases in non-local envs
            if (! $allowDestructive && $env !== 'local') {
                foreach (array_filter([$contact, $company, $contactStats, $companyStats]) as $alias) {
                    if (! $alias) {
                        continue;
                    }
                    try {
                        $aliasMap = $this->client->indices()->getAlias($this->withHeaders(['name' => $alias]))->asArray();
                        foreach (array_keys($aliasMap) as $index) {
                            $this->client->indices()->putSettings($this->withHeaders([
                                'index' => $index,
                                'body' => ['index' => ['blocks' => ['delete' => true]]],
                            ]));
                        }
                    } catch (Exception $e) {
                        // If alias missing or API fails, ignore
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore safety application errors (e.g., index missing or already immutable)
            Log::channel('elastic')->warning('Elastic safety apply failed', ['error' => $e->getMessage()]);
        }
    }

    public function ensureAlias(string $alias, array $settings = [], array $mapping = []): void
    {
        // Ensure alias exists by creating a versioned index and attaching alias if missing
        try {
            $exists = $this->client->indices()->existsAlias($this->withHeaders(['name' => $alias]))->asBool();
            if ($exists) {
                return;
            }
        } catch (Exception $e) {
            // proceed to create when call fails
        }
        $suffix = '_v'.date('YmdHis');
        $index = $alias.$suffix;
        if (! $this->client->indices()->exists($this->withHeaders(['index' => $index]))->asBool()) {
            $this->client->indices()->create($this->withHeaders([
                'index' => $index,
                'body' => ['settings' => $settings, 'mappings' => $mapping],
            ]));
        }
        $this->client->indices()->updateAliases($this->withHeaders([
            'body' => ['actions' => [['add' => ['index' => $index, 'alias' => $alias]]]],
        ]));
    }

    protected function withHeaders(array $params): array
    {
        $clientOpts = $params['client'] ?? [];
        $clientOpts['headers'] = array_merge($clientOpts['headers'] ?? [], $this->defaultHeaders);
        $params['client'] = $clientOpts;

        return $params;
    }

    /**
     * Index a document
     *
     * @param  array|string  $body
     * @param  array  $options  Additional indexing options
     *
     * @throws Exception
     */
    public function indexDocument(string $index, $body, ?string $id = null, array $options = []): array
    {
        Log::channel('elastic')->debug('Starting document indexing', [
            'index' => $index,
            'id' => $id ?? 'auto-generated',
            'options' => $options,
            'document_size' => is_array($body) ? strlen(json_encode($body)) : strlen($body),
        ]);

        try {
            $params = array_merge([
                'index' => $index,
                'body' => $body,
                'refresh' => $options['refresh'] ?? true,
            ], $options);

            if ($id !== null) {
                $params['id'] = $id;
            }

            $response = $this->client->index($this->withHeaders($params));

            Log::channel('elastic')->debug('Document indexed successfully', [
                'index' => $index,
                'id' => $id ?? 'auto-generated',
                'result' => $response['result'] ?? 'unknown',
                'version' => $response['_version'] ?? null,
            ]);

            return $response->asArray();
        } catch (ClientResponseException $e) {
            Log::channel('elastic')->error('Failed to index document: Client error', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
                'status' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (ServerResponseException $e) {
            Log::channel('elastic')->error('Failed to index document: Server error', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
                'status' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get a document by ID
     *
     * @param  array  $options  Additional options
     *
     * @throws Exception
     */
    public function getDocument(string $index, string $id, array $options = []): array
    {
        Log::channel('elastic')->debug('Fetching document by id', [
            'index' => $index,
            'id' => $id,
            'options' => $options,
        ]);

        try {
            $params = array_merge([
                'index' => $index,
                'id' => $id,
            ], $options);

            $response = $this->client->get($this->withHeaders($params))->asArray();

            Log::channel('elastic')->info('Document retrieved by id successfully', [
                'index' => $index,
                'id' => $id,
                'version' => $response['_version'] ?? null,
            ]);

            return $response;
        } catch (ClientResponseException $e) {
            Log::channel('elastic')->error('Failed to get document by id', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
                'status' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Perform a search query
     *
     * @param  array  $params  Search parameters
     *
     * @throws Exception
     */
    public function search(array $params): array
    {
        Log::channel('elastic')->debug('Executing search query', [
            'index' => $params['index'] ?? 'all',
            'query' => json_encode($params['body']) ?? [],
        ]);

        try {
            $response = $this->client->search($this->withHeaders($params))->asArray();

            Log::channel('elastic')->debug('Elastic Response', ['response' => json_encode($response)]);

            Log::channel('elastic')->info('Search completed successfully', [
                'index' => $params['index'] ?? 'all',
                'total_hits' => $response['hits']['total']['value'] ?? 0,
                'max_score' => $response['hits']['max_score'] ?? null,
                'took' => $response['took'] ?? null,
            ]);

            return $response;
        } catch (ClientResponseException $e) {
            Log::channel('elastic')->error('Search query failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'status' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if the cluster is available
     */
    public function ping(): bool
    {
        Log::channel('elastic')->debug('Pinging Elasticsearch cluster');
        try {
            // Use an info call with headers to ensure auth/compat headers are applied
            $this->client->info($this->withHeaders([]))->asArray();

            return true;
        } catch (Exception $e) {
            Log::channel('elastic')->error('Elasticsearch cluster is not responding', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create or update an index mapping
     *
     * @throws Exception
     */
    public function putMapping(string $index, array $mapping): array
    {
        Log::channel('elastic')->debug('Updating index mapping', [
            'index' => $index,
            'mapping' => $mapping,
        ]);

        try {
            $response = $this->client->indices()->putMapping($this->withHeaders([
                'index' => $index,
                'body' => $mapping,
            ]))->asArray();

            Log::channel('elastic')->info('Mapping updated successfully', [
                'index' => $index,
                'acknowledged' => $response['acknowledged'] ?? false,
            ]);

            return $response;

        } catch (Exception $e) {
            Log::channel('elastic')->error('Failed to update mapping', [
                'index' => $index,
                'error' => $e->getMessage(),
                'status' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the raw Elasticsearch client instance
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
