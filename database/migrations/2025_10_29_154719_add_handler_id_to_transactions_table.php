<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // คำสั่งสำหรับ "เพิ่ม" คอลัมน์
        Schema::table('transactions', function (Blueprint $table) {
            // เพิ่มคอลัมน์ handler_id ให้เป็นตัวเลข (เหมือน user_id)
            // กำหนดให้เป็น nullable() คือ "อนุญาตให้เว้นว่างได้" (เผื่อบาง transaction ไม่มี handler)
            // และวางไว้หลังคอลัมน์ 'user_id' เพื่อความสวยงาม
            $table->unsignedBigInteger('handler_id')->nullable()->after('user_id');
            
            // หมายเหตุ: เราไม่สร้าง Foreign Key constraint 
            // เพราะ 'handler_id' อาจจะอ้างอิง ID จากตาราง users ที่อยู่คนละฐานข้อมูล (depart_it_db)
            // $table->foreign('handler_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // คำสั่งสำหรับ "ย้อนกลับ" (ลบ) คอลัมน์ ถ้าต้องการ rollback
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('handler_id');
        });
    }
};
