<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FilterRegistry;

// Find the filter correctly
$filter = FilterRegistry::where('id', 'keywords')->first();

if (!$filter) {
    echo "Keywords filter not found in DB. Code fallback should have worked.\n";
    exit(1);
}

echo "Current Config:\n";
print_r($filter->filtering_config);

$config = $filter->filtering_config ?? [];
$config['split_on_comma'] = true; // Enable the flag

$filter->filtering_config = $config;
$filter->save();

echo "\nUpdated Config:\n";
print_r($filter->filtering_config);
echo "\nSuccessfully updated DB record.\n";
