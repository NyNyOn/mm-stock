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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // เพิ่ม field ราคาต่อหน่วย (unit_price)
            if (!Schema::hasColumn('purchase_order_items', 'unit_price')) {
                $table->decimal('unit_price', 10, 2)->default(0)->after('quantity_ordered');
            }
            
            // เพิ่ม field หน่วยนับ (unit_name) เผื่อไว้ด้วยเลย
            if (!Schema::hasColumn('purchase_order_items', 'unit_name')) {
                $table->string('unit_name', 50)->nullable()->default('ea')->after('quantity_ordered');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'unit_name']);
        });
    }
};