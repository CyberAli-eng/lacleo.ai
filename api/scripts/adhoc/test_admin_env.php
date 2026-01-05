<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking ADMIN_EMAILS environment variable...\n";
$envValue = env('ADMIN_EMAILS');
echo "Raw ENV value: " . var_export($envValue, true) . "\n";

$adminEmails = array_map('strtolower', array_filter(array_map('trim', explode(',', (string) $envValue))));
echo "Parsed Emails:\n";
print_r($adminEmails);

if (in_array('shaizqurashi12345@gmail.com', $adminEmails)) {
    echo "SUCCESS: shaizqurashi12345@gmail.com is correctly recognized as an admin.\n";
} else {
    echo "WARNING: shaizqurashi12345@gmail.com is NOT in the admin list. Check your .env file.\n";
}
