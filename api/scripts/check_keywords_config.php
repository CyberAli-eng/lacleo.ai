<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\FilterRegistry;

$filters = FilterRegistry::getFilters();
$keywordsFilter = null;

foreach ($filters as $f) {
    if (($f['id'] ?? '') === 'keywords') {
        $keywordsFilter = $f;
        break;
    }
}

if ($keywordsFilter) {
    echo "Keywords Filter Found:\n";
    print_r($keywordsFilter['filtering'] ?? 'No filtering config');

    if (empty($keywordsFilter['filtering']['split_on_comma'])) {
        echo "\n\n[FAIL] 'split_on_comma' is MISSING or FALSE.\n";
        echo "This indicates the app is likely loading from the DB without the update.\n";
    } else {
        echo "\n\n[PASS] 'split_on_comma' is TRUE.\n";
    }
} else {
    echo "Keywords Filter NOT FOUND in registry.\n";
}
