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
                // เพิ่มคอลัมน์ confirmed_at หลังคอลัมน์ user_confirmed_at (หรือตำแหน่งที่เหมาะสม)
                // เป็น timestamp ที่บันทึกเวลาที่ Transaction เสร็จสมบูรณ์
                // ตั้งค่าเป็น nullable เพราะ Transaction ที่ยังไม่เสร็จจะไม่มีค่านี้
                $table->timestamp('confirmed_at')->nullable()->after('user_confirmed_at');
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::table('transactions', function (Blueprint $table) {
                // ลบคอลัมน์ confirmed_at
                $table->dropColumn('confirmed_at');
            });
        }
    };


    
