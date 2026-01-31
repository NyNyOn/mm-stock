<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ลบตารางเก่าและสร้างใหม่ (ลบ columns ที่ไม่ใช้: q1-q3, rating_score, rating, answers)
     */
    public function up(): void
    {
        // ลบตารางเก่า
        Schema::dropIfExists('equipment_ratings');
        
        // สร้างใหม่ด้วย columns ที่จำเป็น
        Schema::create('equipment_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('user_id')->nullable(); // ผู้ให้คะแนน
            $table->enum('feedback_type', ['good', 'neutral', 'bad'])->nullable(); // ประเภทความคิดเห็น
            $table->text('comment')->nullable(); // ความคิดเห็น
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('transaction_id');
            $table->index('equipment_id');
            $table->index('feedback_type');
            
            // Optional: Foreign keys (ถ้าต้องการ)
            // $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            // $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_ratings');
    }
};
