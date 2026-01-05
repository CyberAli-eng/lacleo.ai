<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new \App\Http\Controllers\Api\v1\ExportController();

echo "--- TEST 1: Selected Mode (Ids present) ---\n";
// Attempt to mimic request with IDs
$req1 = \Illuminate\Http\Request::create('/api/v1/billing/preview-export', 'POST', [
    'type' => 'contacts',
    'ids' => ['test_contact_1', 'test_contact_2'], // Fake IDs, should fall through to logic
    'sanitize' => false,
    'fields' => ['email' => true, 'phone' => true],
    'limit' => 2 // Matches exportCount
]);
// Manually set validation rules in controller to match what I want to test
// Actually, I can't easily swap the code inside controller without editing file.
// So I will just run the controller method AS IS (with 'required|array').
try {
    $resp1 = $controller->preview($req1);
    echo "Status: " . $resp1->getStatusCode() . "\n";
    print_r($resp1->getData(true));
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- TEST 2: Custom Mode (Empty Ids) ---\n";
$req2 = \Illuminate\Http\Request::create('/api/v1/billing/preview-export', 'POST', [
    'type' => 'contacts',
    'ids' => [],
    'sanitize' => false,
    'fields' => ['email' => true, 'phone' => true],
    'limit' => 1000,
    'filter_dsl' => [] // Empty DSL
]);
try {
    $resp2 = $controller->preview($req2);
    echo "Status: " . $resp2->getStatusCode() . "\n";
    print_r($resp2->getData(true));
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
