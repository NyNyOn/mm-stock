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
            $table->timestamp('last_cleared_at')->nullable()->after('last_notification_check');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_meta', function (Blueprint $table) {
            $table->dropColumn('last_cleared_at');
        });
    }
};
