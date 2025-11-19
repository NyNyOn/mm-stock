<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // ขยายคอลัมน์ 'type' ให้รองรับได้ 20 ตัวอักษร
            $table->string('type', 25)->change(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // (Optional: ทำให้ย้อนกลับได้)
            $table->string('type', 10)->change(); // หรือค่าเดิมที่คุณตั้งไว้
        });
    }
};