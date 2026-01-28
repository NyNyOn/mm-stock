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
        Schema::table('equipments', function (Blueprint $table) {
            $table->float('smart_rating')->nullable()->after('price')->comment('Official Smart Rating (0.00-5.00)');
            $table->dateTime('last_rating_update')->nullable()->after('smart_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn(['smart_rating', 'last_rating_update']);
        });
    }
};
