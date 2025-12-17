<?php

namespace App\Console\Commands;

use App\Elasticsearch\ElasticClient;
use Illuminate\Console\Command;

class ElasticAliasSwap extends Command
{
    protected $signature = 'elastic:alias-swap {alias} {index} {--remove=* : Indices to remove from alias before adding}';

    protected $description = 'Atomically point an alias to a target index, removing prior indices';

    public function handle(ElasticClient $elastic): int
    {
        $client = $elastic->getClient();
        $alias = $this->argument('alias');
        $index = $this->argument('index');
        $remove = (array) $this->option('remove');

        $actions = [];
        foreach ($remove as $r) {
            if ($r) {
                $actions[] = ['remove' => ['index' => $r, 'alias' => $alias]];
            }
        }
        $actions[] = ['add' => ['index' => $index, 'alias' => $alias]];

        $client->indices()->updateAliases(['body' => ['actions' => $actions]]);
        $this->info("Alias {$alias} now points to {$index}");

        return 0;
    }
}
