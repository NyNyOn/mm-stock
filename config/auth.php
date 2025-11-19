<?php

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // ✅ เพิ่ม guard ใหม่ของเรา
        'ldap' => [
            'driver' => 'session',
            'provider' => 'ldap_users', // บอกให้ "ยาม" คนนี้ใช้ provider ด้านล่าง
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        // ✅ เพิ่ม provider ใหม่ของเรา
        'ldap_users' => [
            'driver' => 'ldap', // 'ldap' คือชื่อ driver ที่เราตั้งใน AuthServiceProvider
            'model' => App\Models\LdapUser::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

    'super_admin_id' => env('SUPER_ADMIN_USER_ID'),
];
