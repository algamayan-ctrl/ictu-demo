<?php

use Illuminate\Support\Str;

return [

    // This is the configuration for Default Session Driver.

    'driver' => env('SESSION_DRIVER', 'database'),

    // This is the configuration for Session Lifetime.

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    // This is the configuration for User Idle Timeout.

    'idle_timeout' => (int) env('SESSION_IDLE_TIMEOUT', 15),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    // This is the configuration for Session Encryption.

    'encrypt' => env('SESSION_ENCRYPT', false),

    // This is the configuration for Session File Location.

    'files' => storage_path('framework/sessions'),

    // This is the configuration for Session Database Connection.

    'connection' => env('SESSION_CONNECTION'),

    // This is the configuration for Session Database Table.

    'table' => env('SESSION_TABLE', 'sessions'),

    // This is the configuration for Session Cache Store.

    'store' => env('SESSION_STORE'),

    // This is the configuration for Session Sweeping Lottery.

    'lottery' => [2, 100],

    // This is the configuration for Session Cookie Name.

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    // This is the configuration for Session Cookie Path.

    'path' => env('SESSION_PATH', '/'),

    // This is the configuration for Session Cookie Domain.

    'domain' => env('SESSION_DOMAIN'),

    // This is the configuration for HTTPS Only Cookies.

    'secure' => env('SESSION_SECURE_COOKIE'),

    // This is the configuration for HTTP Access Only.

    'http_only' => env('SESSION_HTTP_ONLY', true),

    // This is the configuration for Same-Site Cookies.

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    // This is the configuration for Partitioned Cookies.

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

    // This is the configuration for Session Serialization.

    'serialization' => 'json',

];
