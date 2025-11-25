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
    Schema::table('consumable_returns', function (Blueprint $table) {
        // action_type: 'return' = คืนของ, 'write_off' = ใช้หมด/ตัดจำหน่าย
        $table->string('action_type')->default('return')->after('quantity_returned');
    });
}

public function down()
{
    Schema::table('consumable_returns', function (Blueprint $table) {
        $table->dropColumn('action_type');
    });
}
};
