<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    // Private evidence uses its own disk; every StoredFile retains the disk
    // it was written to so existing local evidence remains readable.
    'protected' => env('PROTECTED_FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Protected Files Disk
    |--------------------------------------------------------------------------
    |
    | The disk NEW protected files are written to: borrower documents,
    | signatures, evidence, generated controlled documents and profile
    | pictures. It is deliberately separate from the default disk above, so
    | framework defaults and temporary processing never move with it.
    |
    | Every stored_files row records the disk it was written to, and reads
    | always follow that recorded disk. Changing this value therefore never
    | moves, re-points or hides a file that already exists.
    |
    | Localhost keeps "local". A Laravel Cloud environment with a private
    | bucket attached sets PROTECTED_FILES_DISK=s3.
    |
    */

    'protected_disk' => env('PROTECTED_FILES_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
