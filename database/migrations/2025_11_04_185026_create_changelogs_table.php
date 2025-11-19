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
    Schema::create('changelogs', function (Blueprint $table) {
        $table->id();
        $table->date('change_date'); // วันที่อัปเดต
        $table->string('version')->nullable(); // เช่น v1.0.1
        $table->string('type'); // 'feature', 'bugfix', 'improvement'
        $table->string('title'); // หัวข้อ: เช่น "แก้ไขระบบเบิก"
        $table->text('description'); // รายละเอียด: "เปลี่ยนปุ่มเบิกจาก 3 ปุ่มเหลือ 1 ปุ่ม"
        $table->json('files_modified')->nullable(); // เก็บรายชื่อไฟล์ที่แก้ (เช่น ['TransactionController.php', ...])
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('changelogs');
    }
};
