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
        Schema::table('user_meta', function (Blueprint $table) {
            $table->boolean('is_auto_confirm_disabled')->default(false)->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_meta', function (Blueprint $table) {
            $table->dropColumn('is_auto_confirm_disabled');
        });
    }
};
