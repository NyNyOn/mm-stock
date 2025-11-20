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
        // ✅ แก้ไข: เช็คก่อนว่ามีตาราง equipment_ratings หรือยัง
        if (!Schema::hasTable('equipment_ratings')) {
            Schema::create('equipment_ratings', function (Blueprint $table) {
                $table->id();
                
                // เชื่อมโยงกับ Transaction 
                $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
                
                // เชื่อมโยงกับ Equipment
                $table->foreignId('equipment_id')->constrained('equipments')->onDelete('cascade');
                
                $table->integer('rating')->comment('คะแนน 1-5');
                $table->text('comment')->nullable()->comment('ความคิดเห็นเพิ่มเติม');
                
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_ratings');
    }
};