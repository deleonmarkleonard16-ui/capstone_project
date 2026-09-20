<?php

return [
    // Polling works without a websocket server or queue worker.
    'default' => env('BROADCAST_CONNECTION', 'null'),
    'connections' => [
        'null' => ['driver' => 'null'],
        'log' => ['driver' => 'log'],
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => ['host' => env('REVERB_HOST', 'localhost'), 'port' => env('REVERB_PORT', 443), 'scheme' => env('REVERB_SCHEME', 'https'), 'useTLS' => env('REVERB_SCHEME', 'https') === 'https'],
        ],
    ],
];
