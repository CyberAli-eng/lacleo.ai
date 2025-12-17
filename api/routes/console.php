<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Nightly snapshot of indices (requires an ES repository configured)
Artisan::command('elastic:nightly-snapshot', function () {
    $repo = env('ELASTICSEARCH_SNAPSHOT_REPO');
    if (! $repo) {
        $this->error('ELASTICSEARCH_SNAPSHOT_REPO not set');

        return 1;
    }
    $name = 'nightly_'.date('Ymd');
    $indices = implode(',', array_filter([
        env('ELASTICSEARCH_CONTACT_INDEX'),
        env('ELASTICSEARCH_COMPANY_INDEX'),
        env('ELASTICSEARCH_CONTACT_STATS_INDEX') ?: (env('ELASTICSEARCH_CONTACT_INDEX') ? env('ELASTICSEARCH_CONTACT_INDEX').'_stats' : null),
        env('ELASTICSEARCH_COMPANY_STATS_INDEX') ?: (env('ELASTICSEARCH_COMPANY_INDEX') ? env('ELASTICSEARCH_COMPANY_INDEX').'_stats' : null),
    ]));
    app(\App\Elasticsearch\ElasticClient::class)->getClient()->snapshot()->create([
        'repository' => $repo,
        'snapshot' => $name,
        'body' => ['indices' => $indices, 'include_global_state' => false],
    ]);
    $this->info("Snapshot {$repo}/{$name} queued for {$indices}");

    return 0;
})->purpose('Create nightly ES snapshots')->dailyAt('02:30');

Artisan::command('elastic:schedule-health', function () {
    $this->call('elastic:health-check');
})->purpose('Run ES health check')->dailyAt('03:00');
