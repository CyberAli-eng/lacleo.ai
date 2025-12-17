<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\SetupElasticModels::class,
                \App\Console\Commands\EsRestoreFromDb::class,
                \App\Console\Commands\ElasticSnapshot::class,
                \App\Console\Commands\ElasticRestore::class,
            ]);
        }
    }
}
