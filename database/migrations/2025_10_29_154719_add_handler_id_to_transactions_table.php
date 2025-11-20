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
            // ✅ แก้ไข: เช็คก่อนว่ามีคอลัมน์ไหม ถ้ายังไม่มีค่อยสร้าง (ป้องกัน Error Duplicate)
            if (!Schema::hasColumn('transactions', 'handler_id')) {
                $table->unsignedBigInteger('handler_id')->nullable()->after('user_id');
                
                // เพิ่ม Foreign Key (ถ้าจำเป็น)
                // $table->foreign('handler_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'handler_id')) {
                // ลบ Foreign Key ก่อน (ถ้ามีชื่อ index นี้)
                // $table->dropForeign(['handler_id']);
                $table->dropColumn('handler_id');
            }
        });
    }
};