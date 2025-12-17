<?php

namespace App\Console\Commands;

use App\Elasticsearch\ElasticClient;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Console\Command;
use Throwable;

class EsRestoreFromDb extends Command
{
    protected $signature = 'es:restore-from-db {target : contacts|companies} {--batch=1000} {--suffix=} {--alias=}';

    protected $description = 'Rebuild Elasticsearch index from database source of truth with bulk ingestion';

    public function handle(ElasticClient $elastic)
    {
        $target = $this->argument('target');
        $batch = (int) $this->option('batch');
        $suffix = $this->option('suffix') ?: ('_v'.date('YmdHis'));

        if (! in_array($target, ['contacts', 'companies'])) {
            $this->error('Invalid target. Use contacts|companies');

            return 1;
        }
        [$modelClass, $baseIndex] = $target === 'contacts'
            ? [Contact::class, (new Contact)->getIndexName()]
            : [Company::class, (new Company)->getIndexName()];

        $targetIndex = $baseIndex.$suffix;
        $this->info("Creating target index: {$targetIndex}");

        $model = new $modelClass;
        $settings = method_exists($model, 'elasticSettings') ? $model->elasticSettings() : [];
        $mapping = [
            'dynamic' => $model->getDynamicMapSetting(),
            'properties' => array_merge($model->getMappingProperties(), $model->getAddtionalMappingProperties()),
            'dynamic_templates' => $model->getDynamicTemplates(),
        ];

        $client = $elastic->getClient();
        if (! $client->indices()->exists(['index' => $targetIndex])->asBool()) {
            $client->indices()->create([
                'index' => $targetIndex,
                'body' => ['settings' => $settings, 'mappings' => $mapping],
            ]);
        } else {
            $this->warn('Target index already exists, proceeding with ingestion');
        }

        $this->info('Starting DB ingestion...');
        $eloquent = (new $modelClass)->newQuery();
        $total = $eloquent->count();
        $this->info("Total rows: {$total}");

        $processed = 0;
        $failed = 0;
        $eloquent->orderBy('id')->chunk($batch, function ($rows) use (&$processed, &$failed, $client, $targetIndex) {
            $body = [];
            foreach ($rows as $row) {
                try {
                    $doc = $row->toElasticArray();
                    $body[] = ['index' => ['_index' => $targetIndex, '_id' => $row->getKey()]];
                    $body[] = $doc;
                    $processed++;
                } catch (Throwable $e) {
                    $failed++;
                }
            }
            if (! empty($body)) {
                $client->bulk(['body' => $body]);
            }
            if ($processed % 10000 === 0) {
                $this->info("Ingested {$processed} rows...");
            }
        });

        $this->info("Ingestion done. processed={$processed} failed={$failed}");

        $alias = $this->option('alias');
        if ($alias) {
            $this->info("Pointing alias '{$alias}' to {$targetIndex}");
            $client->indices()->updateAliases([
                'body' => [
                    'actions' => [
                        ['add' => ['index' => $targetIndex, 'alias' => $alias]],
                    ],
                ],
            ]);
        }

        return 0;
    }
}
