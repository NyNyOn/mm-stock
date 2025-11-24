<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('equipments', function (Blueprint $table) {
            // เก็บวันที่นับสต็อกล่าสุด (nullable เพราะของใหม่ยังไม่เคยนับ)
            $table->timestamp('last_stock_check_at')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn('last_stock_check_at');
        });
    }
};