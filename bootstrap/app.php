<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ ปลดล็อก CSRF สำหรับ Route ที่รับข้อมูลจาก PU Hub
        $middleware->validateCsrfTokens(except: [
            'notify-hub-arrival', // <-- ใส่ชื่อ URL ที่ต้องการยกเว้น
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();