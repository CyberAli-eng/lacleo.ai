<?php

return [
    'hosts' => [
        env('ELASTICSEARCH_HOST', 'https://localhost').':'.env('ELASTICSEARCH_PORT', 9200),
    ],
    'ssl' => [
        'verify' => false, // Set to true if you want to verify the SSL certificate
        'cafile' => env('ELASTICSEARCH_CA_FILE', '/path/to/ca.crt'),
    ],
    'auth' => [
        'api_key' => env('ELASTICSEARCH_APIKEY'),
        'api_key_secret' => env('ELASTICSEARCH_APIKEY_SECRET'),
        'username' => env('ELASTICSEARCH_USERNAME', 'elastic'),
        'password' => env('ELASTICSEARCH_PASSWORD'),
    ],
    'index_prefix' => env('ELASTICSEARCH_PREFIX', 'local_lacleo'),
    'mappings_path' => base_path(env('ELASTICSEARCH_MAPPING_LOCATION', 'resources/es-mappings/models')),
];
