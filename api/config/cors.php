<?php

return [
    'paths' => ['api/*', '*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://app.lacleo.test:3000',
        'https://app.lacleo.test',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        'https://local-accounts.lacleo.test',
        'https://local-api.lacleo.test',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [
        'X-Request-ID',
        'request_id',
    ],
    'supports_credentials' => true,
];
