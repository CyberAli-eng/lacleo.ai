<?php

return [
    'paths' => ['api/*', '*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),
    'allowed_headers' => ['*'],
    'exposed_headers' => [
        'X-Request-ID',
        'request_id',
    ],
    'supports_credentials' => true,
];
