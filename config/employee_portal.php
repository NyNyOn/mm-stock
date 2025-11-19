<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Employee Portal Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the configuration for your employee portal service.
    |
    */

    'base_uri' => env('EMPLOYEE_PORTAL_URL', 'http://192.168.10.128'), // อ่านค่าจาก .env

    'photo_path' => '/mobilelogin/employee_photos', // Path ที่เก็บรูปภาพ
];
