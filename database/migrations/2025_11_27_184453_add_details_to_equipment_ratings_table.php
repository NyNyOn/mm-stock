<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('equipment_ratings', function (Blueprint $table) {
            // เพิ่มคอลัมน์สำหรับเก็บคำตอบ 3 ข้อ
            // after('equipment_id') คือบอกให้เอาคอลัมน์นี้ไปต่อท้าย equipment_id
            $table->tinyInteger('q1_answer')->nullable()->after('equipment_id')->comment('1=แย่, 2=ไม่ได้ใช้, 3=ดี');
            $table->tinyInteger('q2_answer')->nullable()->after('q1_answer');
            $table->tinyInteger('q3_answer')->nullable()->after('q2_answer');
            
            // เพิ่มคอลัมน์คะแนนเฉลี่ย (ทศนิยม) เช่น 3.67
            $table->float('rating_score', 3, 2)->nullable()->after('q3_answer')->comment('คะแนนเฉลี่ย 1.00-5.00');
            
            // เปลี่ยน rating เดิมให้เป็น nullable (เผื่อไม่ได้ใช้แล้ว แต่อย่าลบข้อมูลเก่า)
            $table->integer('rating')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('equipment_ratings', function (Blueprint $table) {
            // ลบคอลัมน์ออกเมื่อ rollback
            $table->dropColumn(['q1_answer', 'q2_answer', 'q3_answer', 'rating_score']);
            
            // เปลี่ยน rating กลับเป็น required เหมือนเดิม (ถ้าทำได้)
            // หมายเหตุ: การ change() กลับอาจมีปัญหาถ้ามีข้อมูล null อยู่แล้วใน rating
            // $table->integer('rating')->nullable(false)->change(); 
        });
    }
};