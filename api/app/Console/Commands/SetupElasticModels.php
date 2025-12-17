<?php

namespace App\Console\Commands;

use App\Elasticsearch\ElasticClient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class SetupElasticModels extends Command
{
    protected $signature = 'elastic:setup 
                          {model? : The model class to setup index for}
                          {--refresh : Force refresh the index}
                          {--all : Setup indices for all models using ElasticInstanceTrait}';

    protected $description = 'Setup Elasticsearch indices for the application';

    protected ElasticClient $elasticClient;

    public function __construct(ElasticClient $elasticClient)
    {
        parent::__construct();
        $this->elasticClient = $elasticClient;
    }

    public function handle()
    {
        if (! $this->checkElasticsearchConnection()) {
            return 1;
        }

        try {
            $models = $this->getModelsToProcess();
            if (empty($models)) {
                $this->error('No models found to process');

                return 1;
            }

            $this->processModels($models);

            return 0;
        } catch (Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");

            return 1;
        }
    }

    protected function checkElasticsearchConnection(): bool
    {
        if (! $this->elasticClient->ping()) {
            $this->error('Could not connect to Elasticsearch. Please check your configuration and ensure Elasticsearch is running.');

            return false;
        }

        $this->info('Successfully connected to Elasticsearch');

        return true;
    }

    protected function getModelsToProcess(): array
    {
        // If specific model is provided
        if ($modelClass = $this->argument('model')) {
            if (! class_exists($modelClass)) {
                $modelClass = "App\\Models\\{$modelClass}";
            }

            if (! class_exists($modelClass)) {
                throw new Exception("Model class {$modelClass} not found");
            }

            return [$modelClass];
        }

        // If --all option is used or no specific model provided
        if ($this->option('all') || ! $this->argument('model')) {
            return $this->findModelsUsingTrait();
        }

        return [];
    }

    protected function findModelsUsingTrait(): array
    {
        $models = [];
        $modelPath = app_path('Models');

        foreach (File::allFiles($modelPath) as $file) {
            $class = 'App\\Models\\'.substr($file->getFilename(), 0, -4);

            if (class_exists($class)) {
                $reflection = new ReflectionClass($class);
                if ($this->usesTrait($reflection, 'App\\Traits\\HasElasticIndex')) {
                    $models[] = $class;
                }
            }
        }

        return $models;
    }

    protected function usesTrait(ReflectionClass $class, string $traitName): bool
    {
        do {
            $traits = array_keys($class->getTraits());
            if (in_array($traitName, $traits)) {
                return true;
            }
        } while ($class = $class->getParentClass());

        return false;
    }

    protected function processModels(array $models): void
    {
        $refresh = $this->option('refresh');
        if ($refresh) {
            $isLocal = app()->environment('local');
            $allow = filter_var(env('ELASTIC_ALLOW_REFRESH', false), FILTER_VALIDATE_BOOLEAN);
            if (! $isLocal && ! $allow) {
                $this->error('Destructive refresh is disabled outside local unless ELASTIC_ALLOW_REFRESH=true');

                return;
            }
            $this->warn('You are about to roll over indices to new versions and reattach aliases. No deletion will occur.');
            $aliasName = null;
            if ($this->argument('model')) {
                $m = $this->argument('model');
                if (! class_exists($m)) {
                    $m = "App\\Models\\{$m}";
                }
                $aliasName = (new $m)->getIndexName();
            }
            $expected = $aliasName ?? 'confirm';
            $typed = $this->ask('TYPE THE ALIAS NAME TO CONFIRM', $expected);
            if ($typed !== $expected) {
                $this->error('Confirmation mismatch. Aborting.');

                return;
            }
        }

        foreach ($models as $modelClass) {
            try {
                $this->info("Processing index for {$modelClass}...");

                $model = new $modelClass;

                if ($refresh) {
                    $this->info("Rolling over index for {$modelClass} (versioned, alias attach)...");
                    $modelClass::createIndex(false);
                    $this->info("Rollover completed for {$modelClass}");
                } else {
                    // Only create if doesn't exist
                    if (! $this->indexExists($model->getIndexName())) {
                        $modelClass::createIndex(false);
                        $this->info("Index created for {$modelClass}");
                    } else {
                        $this->info("Index already exists for {$modelClass}");
                        // Update mapping
                        $modelClass::updateIndexMapping();
                        $this->info("Updated mapping for {$modelClass}");
                    }
                }

                $this->info("Successfully processed {$modelClass}");
            } catch (Exception $e) {
                $this->error("Failed to process {$modelClass}: {$e->getMessage()}");
            }
        }
    }

    protected function indexExists(string $indexName): bool
    {
        try {
            return $this->elasticClient->getClient()->indices()->exists(['index' => $indexName])->asBool();
        } catch (Exception $e) {
            $this->error("Error checking index existence: {$e->getMessage()}");

            return false;
        }
    }
}
