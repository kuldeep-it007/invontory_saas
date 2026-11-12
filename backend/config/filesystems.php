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
            'throw' => false,
            'report' => false,
        ],

        /*
        | Oracle Cloud (OCI) Object Storage via its S3-compatible API.
        |
        | Endpoint: https://<namespace>.compat.objectstorage.<region>.oraclecloud.com
        | Keys:     an OCI "Customer Secret Key" (Profile → Customer secret keys).
        | OCI requires path-style addressing. Recent AWS SDKs send CRC checksums
        | by default, which S3-compatible stores do not all accept, so checksums
        | are only sent/validated when an operation requires them.
        */
        'oci' => [
            'driver' => 's3',
            'key' => env('OCI_ACCESS_KEY_ID'),
            'secret' => env('OCI_SECRET_ACCESS_KEY'),
            'region' => env('OCI_REGION'),
            'bucket' => env('OCI_BUCKET'),
            'endpoint' => env('OCI_ENDPOINT') ?: (env('OCI_NAMESPACE') && env('OCI_REGION')
                ? sprintf('https://%s.compat.objectstorage.%s.oraclecloud.com', env('OCI_NAMESPACE'), env('OCI_REGION'))
                : null),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            'throw' => true,
            'report' => true,
        ],

        /*
        | Local fallback for user uploads when no object storage is configured
        | (development / zero-config boot). Not web-served directly: files are
        | streamed through GET /api/media/... like the object-storage files.
        */
        'uploads_local' => [
            'driver' => 'local',
            'root' => storage_path('app/uploads'),
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads disk
    |--------------------------------------------------------------------------
    |
    | Where user-uploaded files (logos, favicons, profile pictures) are stored.
    | "oci" = Oracle Object Storage; defaults to the local fallback so the app
    | boots with zero configuration.
    |
    */

    'uploads_disk' => env('UPLOADS_DISK', 'uploads_local'),

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
