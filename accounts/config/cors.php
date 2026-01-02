<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'logout', 'user'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://app.lacleo.test:3000',
        'https://app.lacleo.test',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        'https://local-accounts.lacleo.test',
        'https://local-api.lacleo.test',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-Request-ID',
        'request_id',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];
