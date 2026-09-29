<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        if (Schema::hasIndex('service_requests', 'idx_active_request_per_student')) {
            DB::statement('DROP INDEX idx_active_request_per_student ON service_requests');
        }
        DB::statement("ALTER TABLE service_requests MODIFY COLUMN active_guard CHAR(1) AS
            (CASE WHEN archived_at IS NULL AND status IN
                ('pending','receipt-uploaded','proof_review','approved','processing','in-progress','ready','scheduled')
                THEN '1' ELSE NULL END) STORED");
        DB::statement('CREATE UNIQUE INDEX idx_active_request_per_student ON service_requests (student_number, service, active_guard)');

        if (Schema::hasIndex('guidance_appointments', 'idx_active_appointment_per_student')) {
            DB::statement('DROP INDEX idx_active_appointment_per_student ON guidance_appointments');
        }
        DB::statement("ALTER TABLE guidance_appointments MODIFY COLUMN active_guard CHAR(1) AS
            (CASE WHEN is_archived = 0 AND status IN ('Pending Payment','Receipt Uploaded','Approved','In-Progress')
                THEN '1' ELSE NULL END) STORED");
        DB::statement('CREATE UNIQUE INDEX idx_active_appointment_per_student ON guidance_appointments (student_id_number, test_category, active_guard)');
    }

    public function down(): void
    {
        // Keep the corrected guards in place when rolling back later migrations.
    }
};
