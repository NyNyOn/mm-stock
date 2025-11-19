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
                // เพิ่มคอลัมน์ glpi_ticket_id หลังคอลัมน์ return_condition (หรือตำแหน่งที่เหมาะสม)
                // เป็น Integer และ nullable เพราะไม่ใช่ทุก Transaction ที่จะผูกกับ GLPI
                // ควรเป็น unsigned เพื่อให้ตรงกับ id ของตาราง glpi_tickets (ถ้ามี)
                $table->unsignedInteger('glpi_ticket_id')->nullable()->after('return_condition');

                // (Optional but Recommended) ถ้าคุณมีตาราง glpi_tickets
                // $table->foreign('glpi_ticket_id')
                //       ->references('id')
                //       ->on('glpi_tickets') // <-- ตรวจสอบชื่อตาราง GLPI tickets ของคุณ
                //       ->onDelete('set null');
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::table('transactions', function (Blueprint $table) {
                // (Optional) ลบ Foreign Key ก่อน ถ้ามีการสร้างไว้
                // $table->dropForeign(['glpi_ticket_id']);

                // ลบคอลัมน์ glpi_ticket_id
                $table->dropColumn('glpi_ticket_id');
            });
        }
    };