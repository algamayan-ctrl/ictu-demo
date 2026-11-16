<?php

return [

    // This is the configuration for Application Name.

    'name' => env('APP_NAME', 'SPMU-ACPMP'),

    'seed_demo_users' => (bool) env('SEED_DEMO_USERS', false),

    // This is the configuration for Application Environment.

    'env' => env('APP_ENV', 'production'),

    // This is the configuration for Application Debug Mode.

    'debug' => (bool) env('APP_DEBUG', false),

    // This is the configuration for Application URL.

    'url' => env('APP_URL', 'http://localhost'),

    /*
    | Redirect browser GET/HEAD requests that arrive through another hostname
    | to APP_URL (RedirectToCanonicalHost). On by default for local
    | development only; enable it elsewhere once the HTTPS proxy is known to
    | forward the public Host header.
    */
    'canonical_redirect' => (bool) env('CANONICAL_HOST_REDIRECT', env('APP_ENV') === 'local'),

    /*
    | Mailbox named on the public Privacy Policy and Terms of Service pages
    | for privacy requests and questions. Falls back to the system sender.
    */
    'privacy_contact' => env('PRIVACY_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),

    // This is the configuration for Application Timezone.

    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),

    // This is the configuration for Application Locale Configuration.

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    // This is the configuration for Encryption Key.

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    // This is the configuration for Maintenance Mode Driver.

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
