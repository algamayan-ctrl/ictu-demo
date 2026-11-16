<?php

return [

    // This is the configuration for Default Filesystem Disk.

    'default' => env('FILESYSTEM_DISK', 'local'),

    // Private evidence uses its own disk; every StoredFile retains the disk
    // it was written to so existing local evidence remains readable.
    'protected' => env('PROTECTED_FILESYSTEM_DISK', 'local'),

    // This is the configuration for Protected Files Disk.

    'protected_disk' => env('PROTECTED_FILES_DISK', 'local'),

    // This is the configuration for Filesystem Disks.

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            /*
             * Private object storage for protected files. Objects are written
             * private and are only ever served through the application's
             * authorized controllers, never by a bucket URL. A failed
             * operation throws, so a write can never fail unnoticed.
             */
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

    ],

    // This is the configuration for Symbolic Links.

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
