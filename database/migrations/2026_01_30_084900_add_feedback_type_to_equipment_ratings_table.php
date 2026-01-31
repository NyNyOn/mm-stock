<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * เพิ่มคอลัมน์ feedback_type สำหรับระบบประเมินแบบใหม่
     * - good = ถูกใจ 👍
     * - neutral = พอใช้ 👌
     * - bad = แย่ 👎
     */
    public function up(): void
    {
        Schema::table('equipment_ratings', function (Blueprint $table) {
            if (!Schema::hasColumn('equipment_ratings', 'feedback_type')) {
                $table->enum('feedback_type', ['good', 'neutral', 'bad'])
                      ->nullable()
                      ->after('rating_score')
                      ->comment('ถูกใจ=good, พอใช้=neutral, แย่=bad');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_ratings', function (Blueprint $table) {
            if (Schema::hasColumn('equipment_ratings', 'feedback_type')) {
                $table->dropColumn('feedback_type');
            }
        });
    }
};
