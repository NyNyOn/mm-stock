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
            // เก็บคะแนนเป็นทศนิยม (เช่น 5.00) ตาม Requirement
            // ใช้ decimal(3, 2) หมายถึงเก็บเลขได้ 3 หลักรวมทศนิยม 2 ตำแหน่ง (Max 9.99)
            // แต่เราใช้จริงแค่ 1.00 - 5.00
            $table->decimal('rating', 3, 2)->nullable()->after('returned_quantity')
                ->comment('คะแนนประเมิน 1.00-5.00');
            
            // เก็บความคิดเห็นเพิ่มเติม (Optional)
            $table->text('rating_comment')->nullable()->after('rating')
                ->comment('เหตุผลการให้คะแนน');
            
            // วันที่กดให้คะแนน
            $table->timestamp('rated_at')->nullable()->after('rating_comment')
                ->comment('วันที่ทำการประเมิน');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_comment', 'rated_at']);
        });
    }
};