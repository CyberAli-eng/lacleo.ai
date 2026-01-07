<?php

return [
    'paths' => ['api/*', '*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'https://app.lacleo.test,http://localhost:3000,http://127.0.0.1:5173,https://lacleo-ai.vercel.app')),
    'allowed_headers' => ['*'],
    'exposed_headers' => [
        'X-Request-ID',
        'request_id',
    ],
    'supports_credentials' => true,
];
