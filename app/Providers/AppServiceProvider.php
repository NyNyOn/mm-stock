<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum; // (มีอยู่แล้ว)

// ❌ ลบ use Illuminate\Foundation\Application;
// ❌ ลบ use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // --- (โค้ด Sanctum เดิมของคุณ ถูกต้องแล้ว) ---
        Sanctum::usePersonalAccessTokenModel(
            \App\Models\PersonalAccessToken::class
        );
        // --- (สิ้นสุดโค้ด Sanctum) ---


        // ✅✅✅ 
        // Logic `afterResolving` และ `shouldntBeInMaintenanceMode` 
        // พร้อม `dd()` ที่เราเคยเพิ่มไว้ ถูกลบออกจากส่วนนี้ทั้งหมดแล้ว
        // ✅✅✅
    }
}

