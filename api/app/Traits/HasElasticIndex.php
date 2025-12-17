<?php

namespace App\Traits;

use App\Elasticsearch\ElasticClient;
use App\Elasticsearch\ElasticQueryBuilder;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

trait HasElasticIndex
{
    protected static ?ElasticClient $elasticClient = null;

    protected static array $indexConfigurations = [];

    protected ?array $elasticMetadata = null;

    /**
     * Initialize the elastic client
     */
    protected static function bootElasticModelTrait(): void
    {
        if (static::$elasticClient === null) {
            static::$elasticClient = app(ElasticClient::class);
        }
    }

    /**
     * Get the Elasticsearch index configuration
     */
    protected function getIndexConfiguration(): array
    {
        $class = static::class;

        if (!isset(static::$indexConfigurations[$class])) {
            static::$indexConfigurations[$class] = [
                'name' => $this->getIndexName(),
                'dynamic' => $this->getDynamicMapSetting(),
                'settings' => $this->getIndexSettings(),
                'dynamic_templates' => $this->getDynamicTemplates(),
                'mapping' => array_merge($this->getMappingProperties(), $this->getAddtionalMappingProperties()),
            ];
        }

        return static::$indexConfigurations[$class];
    }

    /**
     * Get the Elasticsearch index name for the model.
     */
    public function getIndexName(): string
    {
        // If model provides an explicit index name, use it directly
        if (method_exists($this, 'elasticIndex')) {
            return $this->elasticIndex();
        }

        if (property_exists($this, 'elasticIndex')) {
            return $this->elasticIndex;
        }

        // Otherwise, fall back to prefix + model basename
        $prefix = config('elasticsearch.index_prefix', '');
        $basename = strtolower(class_basename($this));

        return trim("{$prefix}_{$basename}", '_');
    }

    /**
     * Get the alias used for reading (searching).
     * Defaults to the main index name (which should be an alias).
     */
    public function getReadAlias(): string
    {
        if (method_exists($this, 'elasticReadAlias')) {
            return $this->elasticReadAlias();
        }
        // If specific _read alias env var exists (optional pattern)
        // But better to let the model override it.
        return $this->getIndexName();
    }

    /**
     * Get the alias used for writing.
     * Defaults to the main index name.
     */
    public function getWriteAlias(): string
    {
        if (method_exists($this, 'elasticWriteAlias')) {
            return $this->elasticWriteAlias();
        }
        return $this->getIndexName();
    }

    /**
     * Get the Elasticsearch dynamic settings for the model.
     */
    public function getDynamicMapSetting(): bool|string
    {
        return method_exists($this, 'dynamicMapSetting') ? $this->dynamicMapSetting() :
            ($this->dynamicMapSetting ?? true);
    }

    public function getDynamicTemplates()
    {
        return method_exists($this, 'dynamicTemplates') ? $this->dynamicTemplates() :
            ($this->dynamicTemplates ?? []);
    }

    /**
     * Get the index settings
     */
    protected function getIndexSettings(): array
    {
        if (method_exists($this, 'elasticSettings')) {
            return $this->elasticSettings();
        }

        if (property_exists($this, 'elasticSettings')) {
            return $this->elasticSettings;
        }

        return [
            'number_of_shards' => 1,
            'number_of_replicas' => 1,
        ];
    }

    /**
     * Get the mapping properties for Elasticsearch
     */
    public function getMappingProperties(): array
    {
        if (method_exists($this, 'elasticMapping')) {
            return $this->elasticMapping();
        }

        if (property_exists($this, 'elasticMapping')) {
            return $this->elasticMapping;
        }

        try {
            $name = strtolower(class_basename($this));

            $mappingPath = config('elasticsearch.mappings_path', '') . "/$name.json";

            if (File::exists($mappingPath)) {
                $mapping = json_decode(File::get($mappingPath), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $mapping;
                }
            }
        } catch (Exception $e) {
            Log::warning("Error loading Elasticsearch mapping by file. Defaulting to fillables {$e->getMessage()}");
        }

        // Generate basic mapping from fillable attributes
        $mapping = [];
        foreach ($this->getFillable() as $field) {
            $mapping[$field] = ['type' => is_numeric($this->$field) ? 'float' : 'text'];
        }

        // Add timestamps if used
        if ($this->usesTimestamps()) {
            $mapping[$this->getCreatedAtColumn()] = ['type' => 'date'];
            $mapping[$this->getUpdatedAtColumn()] = ['type' => 'date'];
        }

        return $mapping;
    }

    /**
     * Get the additional mapping properties for Elasticsearch
     */
    public function getAddtionalMappingProperties(): array
    {
        if (method_exists($this, 'additionalElasticMappingAttributes')) {
            return $this->additionalElasticMappingAttributes();
        }

        if (property_exists($this, 'additionalElasticMappingAttributes')) {
            return $this->additionalElasticMappingAttributes;
        }

        return [];
    }

    /**
     * Create or update the index with mapping and settings
     */
    public static function createIndex(bool $force = false): array
    {
        static::bootElasticModelTrait();
        $instance = new static;
        $config = $instance->getIndexConfiguration();

        try {
            // Alias-based non-destructive creation
            $alias = $config['name'];
            $suffix = '_v' . date('YmdHis');
            $index = $alias . $suffix;

            // Guard against destructive ops
            $env = config('app.env');
            $allowDestructive = filter_var(env('ELASTIC_ALLOW_DESTRUCTIVE', false), FILTER_VALIDATE_BOOLEAN);
            if ($force && $env !== 'local' && !$allowDestructive) {
                throw new Exception('Destructive refresh blocked outside local');
            }

            // Ensure alias exists; create new versioned index and attach alias
            try {
                $exists = static::$elasticClient->getClient()->indices()->exists(['index' => $index])->asBool();
            } catch (\Throwable $e) {
                return ['error' => 'ELASTIC_UNAVAILABLE', 'message' => 'Search backend is unavailable'];
            }
            if (!$exists) {
                static::$elasticClient->getClient()->indices()->create([
                    'index' => $index,
                    'body' => [
                        'settings' => $config['settings'],
                        'mappings' => [
                            'dynamic' => $config['dynamic'],
                            'properties' => $config['mapping'],
                            'dynamic_templates' => $config['dynamic_templates'],
                        ],
                    ],
                ]);
            }

            // Attach alias to the new index (non-destructive)
            static::$elasticClient->getClient()->indices()->updateAliases([
                'body' => ['actions' => [['add' => ['index' => $index, 'alias' => $alias]]]],
            ]);

            return ['created' => $index, 'alias' => $alias];
        } catch (Exception $e) {
            throw new Exception("Failed to create index: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Update the index mapping
     */
    public static function updateIndexMapping(): array
    {
        static::bootElasticModelTrait();
        $instance = new static;
        $config = $instance->getIndexConfiguration();

        // Apply mapping to all indices behind alias
        $client = static::$elasticClient->getClient();
        try {
            $indices = [$config['name']];
            try {
                $aliasGet = $client->indices()->getAlias(['name' => $config['name']])->asArray();
                $indices = array_keys($aliasGet);
            } catch (Exception $e) {
                // if alias get fails, fallback to alias name as index
            }
            $results = [];
            foreach ($indices as $idx) {
                $results[$idx] = static::$elasticClient->putMapping($idx, ['properties' => $config['mapping']]);
            }

            return $results;
        } catch (Exception $e) {
            throw new Exception("Failed to update mapping: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Save the model to Elasticsearch
     */
    public function saveToElastic(array $options = []): array
    {
        static::bootElasticModelTrait();
        $config = $this->getIndexConfiguration();
        $data = $this->toElasticArray();
        // Ensure alias exists
        static::$elasticClient->ensureAlias(
            $config['name'],
            $config['settings'],
            [
                'dynamic' => $config['dynamic'],
                'properties' => $config['mapping'],
                'dynamic_templates' => $config['dynamic_templates'],
            ]
        );
        Log::debug('Elastic Data', ['Elastic Data' => $data]);

        return static::$elasticClient->indexDocument(
            $config['name'],
            $data,
            $this->getKey(),
            $options
        );
    }

    /**
     * Find a document by ID in Elasticsearch
     *
     * @param  string|int  $id
     * @param  array  $options  Additional options for retrieval
     */
    public static function findInElastic($id, array $options = [])
    {
        static::bootElasticModelTrait();
        $instance = new static;
        $config = $instance->getIndexConfiguration();

        $indexName = is_string($options['index'] ?? null) ? $options['index'] : $config['name'];

        $document = static::$elasticClient->getDocument(
            $indexName,
            $id,
            $options
        );

        Log::debug('details', [
            'id' => $id,
            'name' => $config['name'],
            'document' => $document,
        ]);

        return static::makeFromElasticDocument($document);

    }

    /**
     * Delete from Elasticsearch
     */
    // public function deleteFromElastic(array $options = []): array
    // {
    //     static::bootElasticModelTrait();
    //     $config = $this->getIndexConfiguration();

    //     return static::$elasticClient->deleteDocument(
    //         $config['name'],
    //         $this->getKey(),
    //         $options
    //     );
    // }

    /**
     * Filter data according to elastic mapping
     */
    protected function filterByMapping(array $data, array $mapping): array
    {
        $filtered = [];

        foreach ($mapping as $field => $map) {
            if (!isset($data[$field])) {
                continue;
            }

            if (!isset($map['properties'])) {
                $filtered[$field] = $data[$field];

                continue;
            }

            $isNested = $map['type'] ?? '' === 'nested';
            if ($isNested && is_array($data[$field])) {
                $filtered[$field] = array_map(
                    fn($item) => is_array($item) ? $this->filterByMapping($item, $map['properties']) : $item,
                    $data[$field]
                );
            } elseif (is_array($data[$field])) {
                $filtered[$field] = $this->filterByMapping($data[$field], $map['properties']);
            }
        }

        return $filtered;
    }

    /**
     * Convert the model to Elasticsearch array
     */
    protected function toElasticArray(): array
    {
        // Get base attributes
        $data = array_intersect_key(
            $this->toArray(),
            array_flip($this->getFillable())
        );

        // Add computed attributes
        if (method_exists($this, 'computedElasticAttributes')) {
            $data = array_merge($data, $this->computedElasticAttributes());
        }

        // Transform data if needed
        if (method_exists($this, 'transformElasticAttributes')) {
            $data = $this->transformElasticAttributes($data);
        }

        // Add any custom attributes
        if (method_exists($this, 'additionalElasticAttributes')) {
            $data = array_merge($data, $this->additionalElasticAttributes());
        }

        return $this->filterByMapping($data, $this->getMappingProperties());
    }

    /**
     * Create model instance from Elasticsearch document
     */
    protected static function makeFromElasticDocument(array $document): ?static
    {
        if (empty($document['_source'] ?? null)) {
            return null;
        }

        $instance = new static;

        // Fill the model with document source
        $instance->fill($document['_source']);

        // Store Elasticsearch metadata
        $instance->elasticMetadata = [
            'id' => $document['_id'] ?? null,
            'version' => $document['_version'] ?? null,
            'score' => $document['_score'] ?? null,
            'index' => $document['_index'] ?? null,
            'highlight' => $document['highlight'] ?? null,
        ];

        return $instance;
    }

    /**
     * Get highlights from search results
     */
    public function getHighlights(): ?array
    {
        return $this->elasticMetadata['highlight'] ?? null;
    }

    /**
     * Get Elasticsearch metadata for this instance
     */
    public function getElasticMetadata(): ?array
    {
        return $this->elasticMetadata;
    }

    /**
     * Search documents in Elasticsearch
     */
    public static function searchInElastic(array $query, array $options = [])
    {
        static::bootElasticModelTrait();
        $instance = new static;
        $config = $instance->getIndexConfiguration();
        $indexOverride = $options['index'] ?? null;
        $index = is_string($indexOverride) ? $indexOverride : $instance->getReadAlias();
        try {
            $response = static::$elasticClient->search(array_merge([
                'index' => $index,
                'body' => $query,
            ], $options));

            return $response;
        } catch (\Throwable $e) {
            \Log::channel('elastic')->error('Elasticsearch search failed, returning empty results', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);

            return [
                'hits' => [
                    'total' => ['value' => 0, 'relation' => 'eq'],
                    'max_score' => null,
                    'hits' => [],
                ],
                'aggregations' => [],
                'took' => 0,
            ];
        }

    }

    /**
     * Check if document exists in Elasticsearch
     */
    public function existsInElastic(): bool
    {
        static::bootElasticModelTrait();
        $config = $this->getIndexConfiguration();

        try {
            return static::$elasticClient->getClient()->exists([
                'index' => $config['name'],
                'id' => $this->getKey(),
            ])->asBool();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Refresh the Elasticsearch index
     */
    public static function refreshIndex(): array
    {
        static::bootElasticModelTrait();
        $instance = new static;
        $config = $instance->getIndexConfiguration();

        return static::$elasticClient->getClient()->indices()->refresh([
            'index' => $config['name'],
        ])->asArray();
    }

    /**
     * Create a new Elasticsearch query builder for the model.
     */
    public static function query(): ElasticQueryBuilder
    {
        return new ElasticQueryBuilder(static::class);
    }

    /**
     * Start a new elastic search query
     */
    public static function elastic(): ElasticQueryBuilder
    {
        return static::query();
    }
}
