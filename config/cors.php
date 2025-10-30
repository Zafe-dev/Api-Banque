<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'docs', 'docs/*', 'api/documentation'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*', 'https://seck-moustapha-sn.onrender.com'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 86400,

    'supports_credentials' => false,
];