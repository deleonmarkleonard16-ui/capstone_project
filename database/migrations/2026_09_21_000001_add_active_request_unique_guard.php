<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a database-level guard against duplicate active service requests.
 *
 * Strategy (MySQL 8 / MariaDB 10.2+):
 *   A stored generated column `active_guard` is set to '1' only when the row is
 *   in an active status, NULL otherwise. Because SQL unique indexes treat NULL
 *   values as distinct from each other, completed/void rows never compete with
 *   new submissions — only rows that are still active block a second insert.
 *
 *   The composite unique index (student_number, service, active_guard) therefore
 *   enforces: "at most one active row per student per service".
 *
 * Deployment Safety:
 *   - Both up() and down() methods guard column additions and removals with
 *     Schema::hasColumn() checks to handle interrupted or retry migrations
 *     (e.g., Render deploys against Railway MySQL).
 *   - Index creation and drops check Schema::hasIndex() inside try-catch blocks.
 */
return new class extends Migration
{
    /** Statuses that constitute an "active" request. */
    private const ACTIVE = "'pending','proof_review','approved','processing','ready','scheduled'";

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // 1) Guard for service_requests.active_guard column
        if (! Schema::hasColumn('service_requests', 'active_guard')) {
            if ($driver === 'mysql') {
                DB::statement("
                    ALTER TABLE service_requests
                    ADD COLUMN active_guard CHAR(1)
                        AS (CASE WHEN status IN (" . self::ACTIVE . ") THEN '1' ELSE NULL END)
                        STORED
                ");
            } else {
                Schema::table('service_requests', function (Blueprint $table) {
                    $table->string('active_guard', 1)->nullable();
                });
            }
        }

        // 2) Guard for service_requests unique index
        if ($driver === 'mysql') {
            try {
                if (! Schema::hasIndex('service_requests', 'idx_active_request_per_student')) {
                    DB::statement("
                        CREATE UNIQUE INDEX idx_active_request_per_student
                        ON service_requests (student_number, service, active_guard)
                    ");
                }
            } catch (\Throwable) {
                // Index already exists
            }
        }

        // 3) Guard for guidance_appointments.active_guard column
        if (! Schema::hasColumn('guidance_appointments', 'active_guard')) {
            if ($driver === 'mysql') {
                DB::statement("
                    ALTER TABLE guidance_appointments
                    ADD COLUMN active_guard CHAR(1)
                        AS (CASE WHEN status IN ('Pending Payment','Receipt Uploaded','Approved','In-Progress')
                            THEN '1' ELSE NULL END)
                        STORED
                ");
            } else {
                Schema::table('guidance_appointments', function (Blueprint $table) {
                    $table->string('active_guard', 1)->nullable();
                });
            }
        }

        // 4) Guard for guidance_appointments unique index
        if ($driver === 'mysql') {
            try {
                if (! Schema::hasIndex('guidance_appointments', 'idx_active_appointment_per_student')) {
                    DB::statement("
                        CREATE UNIQUE INDEX idx_active_appointment_per_student
                        ON guidance_appointments (student_id_number, test_category, active_guard)
                    ");
                }
            } catch (\Throwable) {
                // Index already exists
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // 1) Safely drop index on guidance_appointments if present
        if ($driver === 'mysql') {
            try {
                if (Schema::hasIndex('guidance_appointments', 'idx_active_appointment_per_student')) {
                    DB::statement('DROP INDEX idx_active_appointment_per_student ON guidance_appointments');
                }
            } catch (\Throwable) {
                // Index already absent
            }
        }

        // 2) Safely drop active_guard on guidance_appointments if present
        if (Schema::hasColumn('guidance_appointments', 'active_guard')) {
            Schema::table('guidance_appointments', function (Blueprint $table) {
                $table->dropColumn('active_guard');
            });
        }

        // 3) Safely drop index on service_requests if present
        if ($driver === 'mysql') {
            try {
                if (Schema::hasIndex('service_requests', 'idx_active_request_per_student')) {
                    DB::statement('DROP INDEX idx_active_request_per_student ON service_requests');
                }
            } catch (\Throwable) {
                // Index already absent
            }
        }

        // 4) Safely drop active_guard on service_requests if present
        if (Schema::hasColumn('service_requests', 'active_guard')) {
            Schema::table('service_requests', function (Blueprint $table) {
                $table->dropColumn('active_guard');
            });
        }
    }
};
