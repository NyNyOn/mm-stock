<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'depart_it_db' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_IT', '127.0.0.1'),
            'port' => env('DB_PORT_IT', '3306'),
            'database' => env('DB_DATABASE_IT', 'forge'),
            'username' => env('DB_USERNAME_IT', 'forge'),
            'password' => env('DB_PASSWORD_IT', ''),
            'unix_socket' => env('DB_SOCKET_IT', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // ✅ Added Department Connections for Migrations
        'it' => array_merge($default = [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ], ['database' => 'it_stock_db']),

        'hr' => array_merge($default, ['database' => 'hr_stock_db']),
        'qa' => array_merge($default, ['database' => 'qa_stock_db']),
        'pd' => array_merge($default, ['database' => 'pd_stock_db']),
        'wh' => array_merge($default, ['database' => 'wh_stock_db']),
        'en' => array_merge($default, ['database' => 'en_stock_db']),
        'enmold' => array_merge($default, ['database' => 'enmold_stock_db']),


        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

        // --- ✅✅✅ START: แก้ไข Connection สำหรับ GLPI ✅✅✅ ---

        // GLPI IT Connection (ใช้ชื่อ 'glpi_it' แต่ดึงจากตัวแปร '.env' เก่า)
        'glpi_it' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            // ⬇️ ⬇️ ⬇️ กลับไปใช้ตัวแปรเดิม (ที่ไม่มี _IT) ⬇️ ⬇️ ⬇️
            'host' => env('DB_HOST_GLPI', '192.168.10.100'),
            'port' => env('DB_PORT_GLPI', '4008'),
            'database' => env('DB_DATABASE_GLPI', 'itsm_db'),
            'username' => env('DB_USERNAME_GLPI', 'Chj'),
            'password' => env('DB_PASSWORD_GLPI', 'Ch_1njecti0n'),
            // ⬆️ ⬆️ ⬆️ สิ้นสุดส่วนที่แก้ไข ⬆️ ⬆️ ⬆️
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // GLPI EN Connection (ใช้ตัวแปร _EN ใหม่ที่คุณเพิ่มใน .env)
        'glpi_en' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST_GLPI_EN', '192.168.10.100'), // <-- ใช้ _EN suffix (ตาม .env)
            'port' => env('DB_PORT_GLPI_EN', '4008'),     // <-- ใช้ _EN suffix (ตาม .env)
            'database' => env('DB_DATABASE_GLPI_EN', 'ensm_db'), // <-- ใช้ _EN suffix (ตาม .env)
            'username' => env('DB_USERNAME_GLPI_EN', 'Chj'),    // <-- ใช้ _EN suffix (ตาม .env)
            'password' => env('DB_PASSWORD_GLPI_EN', 'Ch_1njecti0n'), // <-- ใช้ _EN suffix (ตาม .env)
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        // --- ✅✅✅ END: สิ้นสุดการแก้ไข Connection ✅✅✅ ---
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];

