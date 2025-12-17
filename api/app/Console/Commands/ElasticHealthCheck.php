<?php

namespace App\Console\Commands;

use App\Elasticsearch\ElasticClient;
use Illuminate\Console\Command;

class ElasticHealthCheck extends Command
{
    protected $signature = 'elastic:health-check';

    protected $description = 'Validate aliases, delete-blocks, snapshots, doc counts, and mapping alignment';

    public function handle(ElasticClient $elastic): int
    {
        $client = $elastic->getClient();
        $aliases = [
            env('ELASTICSEARCH_CONTACT_INDEX'),
            env('ELASTICSEARCH_COMPANY_INDEX'),
            env('ELASTICSEARCH_CONTACT_STATS_INDEX') ?: (env('ELASTICSEARCH_CONTACT_INDEX') ? env('ELASTICSEARCH_CONTACT_INDEX').'_stats' : null),
            env('ELASTICSEARCH_COMPANY_STATS_INDEX') ?: (env('ELASTICSEARCH_COMPANY_INDEX') ? env('ELASTICSEARCH_COMPANY_INDEX').'_stats' : null),
        ];

        foreach ($aliases as $alias) {
            if (! $alias) {
                continue;
            }
            $this->info("Checking alias: {$alias}");
            $exists = $client->indices()->existsAlias(['name' => $alias])->asBool();
            $this->line(' - alias exists: '.($exists ? 'yes' : 'no'));
            $indices = $exists ? array_keys($client->indices()->getAlias(['name' => $alias])->asArray()) : [];
            foreach ($indices as $idx) {
                $count = $client->count(['index' => $idx])->asArray()['count'] ?? 0;
                $this->line("   * index={$idx} count={$count}");
                $settings = $client->indices()->getSettings(['index' => $idx])->asArray();
                $deleteBlocked = data_get($settings, "{$idx}.settings.index.blocks.delete") === 'true';
                $this->line('   * delete-block: '.($deleteBlocked ? 'true' : 'false'));
                try {
                    $mapping = $client->indices()->getMapping(['index' => $idx])->asArray();
                    $props = data_get($mapping, "{$idx}.mappings.properties");
                    $this->line('   * properties: '.(is_array($props) ? count($props) : 0));
                } catch (\Exception $e) {
                    $this->line('   * mapping: (error fetching)');
                }
            }
        }

        $this->info('Snapshot repositories:');
        try {
            $repos = $client->cat()->repositories()->asString();
            $this->line($repos ?: '(none)');
        } catch (\Exception $e) {
            $this->line('(cat repositories failed)');
        }

        $this->info('Health check completed');

        return 0;
    }
}
