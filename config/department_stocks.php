<?php

// config/department_stocks.php
return [

    'default_department' => env('APP_DEFAULT_DEPT', 'mm'),

    'departments' => [
        'mm' => [
            'name' => 'MM',
            'db_name' => env('DB_DATABASE', 'mm_stock_db'),
            'nas_share' => env('NAS_SHARE', 'puims\MMIMS'), // ✅ Get from .env by default
        ],
        'it' => [
            'name' => 'IT',
            'db_name' => 'it_stock_db',
            'nas_share' => 'puims\ITIMS', // ✅ Added NAS Share
        ],
        'hr' => [
            'name' => 'HR',
            'db_name' => 'hr_stock_db',
            'nas_share' => 'puims\HRIMS', // ✅ Added NAS Share
        ],
        'qa' => [
            'name' => 'QA',
            'db_name' => 'qa_stock_db',
            'nas_share' => 'puims\QAIMS', // ✅ Added NAS Share
        ],
        'pd' => [
            'name' => 'PD',
            'db_name' => 'pd_stock_db',
            'nas_share' => 'puims\PDIMS', // ✅ Added NAS Share
        ],
        'wh' => [
            'name' => 'WH',
            'db_name' => 'wh_stock_db',
            'nas_share' => 'puims\WHIMS', // ✅ Added NAS Share
        ],
        'en' => [
            'name' => 'EN',
            'db_name' => 'en_stock_db',
            'nas_share' => 'puims\ENIMS-MNT', // ✅ Added NAS Share
        ],
        'enmold' => [
            'name' => 'Enmold',
            'db_name' => 'enmold_stock_db',
            'nas_share' => 'puims\ENIMS-MOLD', // ✅ Added NAS Share
        ],
    ],
];

