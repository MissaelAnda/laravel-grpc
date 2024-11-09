<?php

return [
    'server' => [
        'host' => env('GRPC_SERVER_HOST', '127.0.0.1'),

        'port' => env('GRPC_SERVER_PORT', 9501),

        'debug' => (bool)env('GRPC_SERVER_DEBUG', env('APP_DEBUG')),

        'cert' => env('GRPC_TLS_CERT', null),

        'key' => env('GRPC_TLS_KEY', null),
    ],

    'client' => [
        'host' => env('GRPC_CLIENT_HOST', '127.0.0.1'),

        'port' => env('GRPC_CLIENT_PORT', 9501),

        'tls' => env('GRPC_TLS_CERT', null),
    ],

    /**
     * The default guard to use in auth, fallbacks to `auth.guard` when null
     */
    'guard' => null,

    /**
     * Global Interceptors, these will be executed for every function call
     */
    'interceptors' => [
        \MissaelAnda\Grpc\Interceptors\InjectMetadataHeaders::class,
    ],

    /**
     * File paths
     */
    'paths' => [
        /**
         * Namespace with directory paths to look for gRPC services, these directories will be scanned recursively
         */
        'services' => [
            'App\\Grpc\\Services' => 'app/Grpc/Services',
        ],

        /**
         * .proto files paths
         */
        'protos' => [
            'protos',
        ],

        /**
         * paths to reload the server when a change occurs
         */
        'watch' => [
            'app/Grpc/Services',
            'app/Grpc/Policies',
            'app/Models',
            'bootstrap',
            'config/**/*.php',
            'resources/**/*.php',
            'composer.lock',
            '.env',
        ],
    ],
];
