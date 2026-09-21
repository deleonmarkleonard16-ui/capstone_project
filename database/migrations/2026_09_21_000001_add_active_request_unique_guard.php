<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a database-level guard against duplicate active service requests.
 *
 * Strategy (MySQL 8 / MariaDB 10.2+):
 *   A stored generated column `active_guard` is set to '1' only when the row is
 *   in an active status, NULL otherwise.  Because SQL unique indexes treat NULL
 *   values as distinct from each other, completed/void rows never compete with
 *   new submissions — only rows that are still active block a second insert.
 *
 *   The composite unique index (student_number, service, active_guard) therefore
 *   enforces: "at most one active row per student per service".
 *
 * SQLite (used in tests) does not support generated columns, so the DB
 * constraint is skipped there.  Application-layer guards in the controller and
 * service class provide the same protection in all environments.
 */
return new class extends Migration
{
    /** Statuses that constitute an "active" request. */
    private const ACTIVE = "'pending','proof_review','approved','processing','ready','scheduled'";

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // 1) Add the generated column to service_requests
            DB::statement("
                ALTER TABLE service_requests
                ADD COLUMN active_guard CHAR(1)
                    AS (CASE WHEN status IN (" . self::ACTIVE . ") THEN '1' ELSE NULL END)
                    STORED
            ");

            // 2) Add the partial-unique index on service_requests
            DB::statement("
                CREATE UNIQUE INDEX idx_active_request_per_student
                ON service_requests (student_number, service, active_guard)
            ");

            // 3) Add the generated column to guidance_appointments
            //    Keyed on student_id_number + test_category for per-test deduplication.
            //    Active GA statuses: Pending Payment, Receipt Uploaded, Approved, In-Progress
            DB::statement("
                ALTER TABLE guidance_appointments
                ADD COLUMN active_guard CHAR(1)
                    AS (CASE WHEN status IN ('Pending Payment','Receipt Uploaded','Approved','In-Progress')
                        THEN '1' ELSE NULL END)
                    STORED
            ");

            // 4) Add the partial-unique index on guidance_appointments
            //    Only applies to rows that have a student_id_number + test_category set
            DB::statement("
                CREATE UNIQUE INDEX idx_active_appointment_per_student
                ON guidance_appointments (student_id_number, test_category, active_guard)
            ");
        }
        // SQLite: no-op — application-layer guards handle it
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX idx_active_appointment_per_student ON guidance_appointments');
            DB::statement('ALTER TABLE guidance_appointments DROP COLUMN active_guard');

            DB::statement('DROP INDEX idx_active_request_per_student ON service_requests');
            DB::statement('ALTER TABLE service_requests DROP COLUMN active_guard');
        }
    }
};
