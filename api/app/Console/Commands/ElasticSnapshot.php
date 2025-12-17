<?php

namespace App\Console\Commands;

use App\Elasticsearch\ElasticClient;
use Illuminate\Console\Command;

class ElasticSnapshot extends Command
{
    protected $signature = 'elastic:snapshot {--repo=} {--name=} {--indices=} {--list}';

    protected $description = 'List repositories/snapshots or create a snapshot of specified indices';

    public function handle(ElasticClient $elastic): int
    {
        $client = $elastic->getClient();

        if ($this->option('list')) {
            $repos = $client->cat()->repositories()->asString();
            $this->line($repos);

            return 0;
        }

        $repo = $this->option('repo');
        $name = $this->option('name') ?: ('lacleo_'.date('YmdHis'));
        $indices = $this->option('indices') ?: implode(',', array_filter([
            env('ELASTICSEARCH_CONTACT_INDEX'),
            env('ELASTICSEARCH_COMPANY_INDEX'),
            env('ELASTICSEARCH_CONTACT_STATS_INDEX') ?: (env('ELASTICSEARCH_CONTACT_INDEX') ? env('ELASTICSEARCH_CONTACT_INDEX').'_stats' : null),
            env('ELASTICSEARCH_COMPANY_STATS_INDEX') ?: (env('ELASTICSEARCH_COMPANY_INDEX') ? env('ELASTICSEARCH_COMPANY_INDEX').'_stats' : null),
        ]));

        if (! $repo) {
            $this->error('Provide --repo for snapshot repository');

            return 1;
        }

        $this->info("Creating snapshot {$repo}/{$name} for indices: {$indices}");
        $client->snapshot()->create([
            'repository' => $repo,
            'snapshot' => $name,
            'body' => [
                'indices' => $indices,
                'include_global_state' => false,
            ],
        ]);
        $this->info('Snapshot initiated');

        return 0;
    }
}
