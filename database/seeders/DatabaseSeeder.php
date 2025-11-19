<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // --- ✅ เพิ่มบรรทัดนี้ ---
        $this->call(PermissionSeeder::class);
        // --- สิ้นสุดส่วนที่เพิ่ม ---

        // User::factory(10)->create();

        // (โค้ดสร้าง User เดิมของคุณ)
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}