<?php
$disk = [

    // di sistem multi tenant, disk local akan dibypass menjadi setingan default storage tenant aktif, bisa s3 bisa local
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app/files'),//base_path('public/upload'),
        'url' => '/storage',
    ],
    
    // di sistem multi tenant, disk public akan dibypass menjadi setingan public storage tenant aktif, bisa s3 bisa local
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        // 'url' => env('APP_URL').'/storage',
        'url' => '/storage/public',
        'visibility' => 'public',
    ],
    
    // disk local khusus multi tenant, untuk file yg general lintas tenant (diakses berbarangan)
    'alltenant' => [ 
        'driver' => 'local',
        'root' => storage_path('app/files/tenant_0'),//base_path('public/upload'),
        'url' => '/storage',
    ],

    // disk local public khusus multi tenant, untuk file yg general lintas tenant (diakses berbarangan)
    'alltenant_public' => [ 
        'driver' => 'local',
        'root' => storage_path('app/public/tenant_0'),
        'url' => '/storage/public',
        'visibility' => 'public',
    ],

    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
    ],
];

$s3count = 1;
while (env('S3_MULTISERVER_'.$s3count.'_ACCESS_KEY_ID',false)) {
    $disk['s3_'.$s3count] = [
        'driver' => 's3',
        'key' => env('S3_MULTISERVER_'.$s3count.'_ACCESS_KEY_ID'),
        'secret' => env('S3_MULTISERVER_'.$s3count.'_SECRET_ACCESS_KEY'),
        'region' => env('S3_MULTISERVER_'.$s3count.'_DEFAULT_REGION'),
        'bucket' => env('S3_MULTISERVER_'.$s3count.'_BUCKET'),
        'url' => env('S3_MULTISERVER_'.$s3count.'_URL'),
        'endpoint' => env('S3_MULTISERVER_'.$s3count.'_ENDPOINT'),
    ];
    $s3count++;
}
$s3count--;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    // apakah menggunakan multi s3 server
    's3_multi_server' => env('S3_MULTISERVER_ENABLE', false),
    's3c_ount'=> $s3count,//jumlah server S3 multitenant nya

    'disks' => $disk,

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
        public_path('public_storage') => storage_path('app/public'),
    ],
];