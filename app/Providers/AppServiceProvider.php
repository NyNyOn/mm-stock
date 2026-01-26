<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

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
        // กำหนดให้ Sanctum ใช้ Model PersonalAccessToken ที่เราสร้างเอง (ถ้ามี)
        Sanctum::usePersonalAccessTokenModel(
            \App\Models\PersonalAccessToken::class
        );

        // ✅✅✅ Load API Settings from Database to Config ✅✅✅
        // ส่วนนี้จะดึงค่าจากตาราง settings มาทับค่าใน config/services.php
        try {
            // เช็คก่อนว่ามีตาราง settings หรือยัง
            if (Schema::hasTable('settings')) {
                $puConfigMap = [
                    // Connection
                    'pu_api_base_url'             => 'services.pu_hub.base_url',
                    'pu_api_token'                => 'services.pu_hub.token',
                    'pu_api_intake_path'          => 'services.pu_hub.intake_path',
                    'pu_api_inspection_path'      => 'services.pu_hub.inspection_path',
                    'pu_api_origin_department_id' => 'services.pu_hub.origin_department_id',
                    
                    // ✅ Priority Mapping (เพิ่มส่วนนี้)
                    'pu_api_priority_scheduled'   => 'services.pu_hub.priorities.scheduled',
                    'pu_api_priority_urgent'      => 'services.pu_hub.priorities.urgent',
                    'pu_api_priority_job'         => 'services.pu_hub.priorities.job',

                    // New: Arrival Notification Path
                    'pu_api_arrival_path'         => 'services.pu_hub.arrival_path',
                ];

                foreach ($puConfigMap as $dbKey => $configKey) {
                    $value = Setting::where('key', $dbKey)->value('value');
                    if ($value) {
                        Config::set($configKey, $value);
                    }
                }
            }
        } catch (\Exception $e) {
            // ปล่อยผ่านกรณี Database ยังไม่พร้อม
        }
    }
}