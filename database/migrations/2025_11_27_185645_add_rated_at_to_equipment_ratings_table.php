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
            // เพิ่มคอลัมน์ rated_at ถ้ายังไม่มี
            if (!Schema::hasColumn('equipment_ratings', 'rated_at')) {
                $table->timestamp('rated_at')->nullable()->after('comment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('equipment_ratings', function (Blueprint $table) {
            if (Schema::hasColumn('equipment_ratings', 'rated_at')) {
                $table->dropColumn('rated_at');
            }
        });
    }
};