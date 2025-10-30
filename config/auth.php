<?php

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Guard API principal - pour les CLIENTS (UUID)
        'api' => [
            'driver' => 'passport',
            'provider' => 'clients',  // ← CHANGEMENT ICI
            'hash' => false,
        ],

        // Guard API pour les ADMINS (bigint)
        'api-admin' => [
            'driver' => 'passport',
            'provider' => 'users',
            'hash' => false,
        ],
    ],

    'providers' => [
        // Provider pour les admins (table users avec UUID)
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],

        // Provider pour les clients (table clients avec id UUID)
        'clients' => [
            'driver' => 'eloquent',
            'model' => App\Models\Client::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        
        'clients' => [
            'provider' => 'clients',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];