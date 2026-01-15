<?php

return [
    /**
     * PU-HUB System Base URL
     * ตั้งค่าใน .env: PUHUB_BASE_URL
     */
    'base_url' => env('PUHUB_BASE_URL', 'https://pu-hub.example.com'),

    /**
     * API Access Token
     * ตั้งค่าใน .env: PUHUB_API_TOKEN
     */
    'api_token' => env('PUHUB_API_TOKEN'),

    /**
     * Webhook Secret สำหรับยืนยันความถูกต้องของ webhook
     * ตั้งค่าใน .env: PUHUB_WEBHOOK_SECRET
     */
    'webhook_secret' => env('PUHUB_WEBHOOK_SECRET'),

    /**
     * Department ID ของแผนกนี้ในระบบ PU-HUB
     * ตั้งค่าใน .env: PUHUB_DEPARTMENT_ID
     */
    'department_id' => env('PUHUB_DEPARTMENT_ID', 1), // 1 = IT
];
