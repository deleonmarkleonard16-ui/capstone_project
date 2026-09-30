<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/*
 * Testing requests are deduplicated by guidance_appointments, whose unique
 * guard includes test_category.  The service_requests guard intentionally
 * excludes testing rows so it continues to protect document services while
 * allowing a student to hold distinct testing categories concurrently.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasIndex('service_requests', 'idx_active_request_per_student')) {
            DB::statement('DROP INDEX idx_active_request_per_student ON service_requests');
        }
        $hasExempt = Schema::hasColumn('service_requests', 'active_guard_exempt');
        $exemptClause = $hasExempt ? 'AND active_guard_exempt = 0' : '';
        DB::statement("ALTER TABLE service_requests MODIFY COLUMN active_guard CHAR(1) AS
            (CASE WHEN service <> 'testing' {$exemptClause}
                AND NULLIF(TRIM(student_number), '') IS NOT NULL
                AND archived_at IS NULL AND status IN
                ('pending','receipt-uploaded','proof_review','approved','processing','in-progress','ready','scheduled')
                THEN '1' ELSE NULL END) STORED");
        DB::statement('CREATE UNIQUE INDEX idx_active_request_per_student ON service_requests (student_number, service, active_guard)');
    }

    public function down(): void
    {
        // The category-level appointment guard remains the correct rule for testing.
    }
};
