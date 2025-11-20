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
            // ตรวจสอบว่ามีคอลัมน์อยู่ไหมก่อนลบ (ป้องกัน Error)
            if (Schema::hasColumn('transactions', 'rating')) {
                $table->dropColumn(['rating', 'rating_comment', 'rated_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->nullable();
            $table->text('rating_comment')->nullable();
            $table->timestamp('rated_at')->nullable();
        });
    }
};