<?php

use Illuminate\Support\Str;

$mysqlPerTenant = [
    'cpanel' => [
        'dbcreate_use_cpanel' => env('CPANEL_CREATEDB_PERTENANT', false),
        'domain' => env('CPANEL_DOMAIN_PERTENANT'),
        'port' => env('CPANEL_PORT_PERTENANT', 2083),
        'username' => env('CPANEL_USERNAME_PERTENANT'),
        'password' => env('CPANEL_PASSWORD_PERTENANT')
    ],
    'name' => env('DB_PERTENANT_NAME', 'Default Tenant DB Server'),
    'driver' => env('DB_PERTENANT_DRIVER', 'mysql'),
    'url' => env('DATABASE_URL'),
    'host' => env('DB_HOST_PERTENANT', '127.0.0.1'),
    'port' => env('DB_PORT_PERTENANT', '3306'),
    'database_prefix' => env('DB_DATABASE_PREFIX_PERTENANT',
        env('DB_DATABASE_PERTENANT',
        env('DB_DATABASE', 'forge'))),
    'database' => env('DB_DATABASE_PERTENANT', env('DB_DATABASE', 'forge')),
    'username' => env('DB_USERNAME_PERTENANT', 'forge'),
    'password' => env('DB_PASSWORD_PERTENANT', ''),
    'unix_socket' => env('DB_SOCKET_PERTENANT', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    // 'strict' => true,
    'modes' => [
        // 'ONLY_FULL_GROUP_BY',
        // 'STRICT_TRANS_TABLES',
        // 'NO_ZERO_IN_DATE',
        // 'NO_ZERO_DATE',
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_AUTO_CREATE_USER',
        // 'NO_ENGINE_SUBSTITUTION',
    ],
    'engine' => 'InnoDB',
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
];

$multiDatabaseServer = [
    'enable' => env('DB_MULTISERVER_ENABLE', false),
    'server_count' => 2, // jumlah db server, minimal 1 (main server)
    'servers' => [
        // server 0 adalah server database yg juga digunakan di default connection
        [
            'name' => env('DB_MULTISERVER_MAIN_NAME', 'Main DB Server'),
            'driver' => env('DB_MULTISERVER_MAIN_DRIVER', 'mysql'),
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            // 'strict' => true,
            'modes' => [
                // 'ONLY_FULL_GROUP_BY',
                // 'STRICT_TRANS_TABLES',
                // 'NO_ZERO_IN_DATE',
                // 'NO_ZERO_DATE',
                'ERROR_FOR_DIVISION_BY_ZERO',
                'NO_AUTO_CREATE_USER',
                // 'NO_ENGINE_SUBSTITUTION',
            ],
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        // server 1 adalah server database multitenant default
        $mysqlPerTenant
    ]
];

$i = 2;
while (env('DB_MULTISERVER_' . $i . '_HOST', false)) {
    $multiDatabaseServer['servers'][] = [
        // config cpanel untuk server lain
        'cpanel' => [
            'dbcreate_use_cpanel' => env('DB_MULTISERVER_' . $i . '_CPANEL_CREATEDB', false),
            'domain' => env('DB_MULTISERVER_' . $i . '_CPANEL_DOMAIN'),
            'port' => env('DB_MULTISERVER_' . $i . '_CPANEL_PORT', 2083),
            'username' => env('DB_MULTISERVER_' . $i . '_CPANEL_USERNAME'),
            'password' => env('DB_MULTISERVER_' . $i . '_CPANEL_PASSWORD')
        ],
        'name' => env('DB_MULTISERVER_' . $i . '_NAME', 'DB Server ' . $i),
        'driver' => env('DB_MULTISERVER_' . $i . '_DRIVER', 'mysql'),
        'url' => env('DATABASE_URL'),
        'host' => env('DB_MULTISERVER_' . $i . '_HOST', '127.0.0.1'),
        'port' => env('DB_MULTISERVER_' . $i . '_PORT', '3306'),
        'database_prefix' => env('DB_MULTISERVER_' . $i . '_DATABASE_PREFIX',
            env('DB_DATABASE_PREFIX_PERTENANT',
                env('DB_DATABASE_PERTENANT',
                    env('DB_DATABASE', 'forge')))),
        'database' => env('DB_MULTISERVER_' . $i . '_DATABASE',
            env('DB_DATABASE_PERTENANT',
                env('DB_DATABASE', 'forge'))),
        'username' => env('DB_MULTISERVER_' . $i . '_USERNAME', 'forge'),
        'password' => env('DB_MULTISERVER_' . $i . '_PASSWORD', ''),
        'unix_socket' => env('DB_MULTISERVER_' . $i . 'SOCKET', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        // 'strict' => true,
        'modes' => [
            // 'ONLY_FULL_GROUP_BY',
            // 'STRICT_TRANS_TABLES',
            // 'NO_ZERO_IN_DATE',
            // 'NO_ZERO_DATE',
            'ERROR_FOR_DIVISION_BY_ZERO',
            'NO_AUTO_CREATE_USER',
            // 'NO_ENGINE_SUBSTITUTION',
        ],
        'engine' => 'InnoDB',
        'options' => extension_loaded('pdo_mysql') ? array_filter([
            PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        ]) : [],

    ];
    $multiDatabaseServer['server_count']++;
    $i++;
}

$mysqlBaseConnection = [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL'),
    'host' => $multiDatabaseServer['servers'][0]['host'],
    'port' => $multiDatabaseServer['servers'][0]['port'],
    'database' => $multiDatabaseServer['servers'][0]['database'],
    'username' => $multiDatabaseServer['servers'][0]['username'],
    'password' => $multiDatabaseServer['servers'][0]['password'],
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    // 'strict' => true,
    'modes' => [
        // 'ONLY_FULL_GROUP_BY',
        // 'STRICT_TRANS_TABLES',
        // 'NO_ZERO_IN_DATE',
        // 'NO_ZERO_DATE',
        'ERROR_FOR_DIVISION_BY_ZERO',
        // 'NO_ENGINE_SUBSTITUTION',
    ],
    'engine' => 'InnoDB',
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
];

return [

    /*
    | Config tambahan untuk multi tenant dan multi database server
    */
    'multi_database_server' => $multiDatabaseServer,

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),
    'perTenant' => env('DB_CONNECTION_PERTENANT', 'mysqlPerTenant'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => $mysqlBaseConnection,
        'mysql0' => $mysqlBaseConnection,

        //config data per tenant
        'mysqlPerTenant' => $mysqlPerTenant,

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => Str::slug(env('APP_NAME', 'laravel'), '_').'_database_',
        ],

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
