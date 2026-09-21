<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Performance indexes for the guidance module queue queries.
     *
     * These composite indexes target the three heaviest query patterns:
     *  1. Module queue  → (service, status, archived_at)
     *  2. Active guard  → (student_number, service, status)
     *  3. Appointment archive → (test_category, is_archived)
     *  4. Admission masterlist sort → (admission_cycle_id, last_name)
     */
    public function up(): void
    {
        // service_requests – module queue filter
        Schema::table('service_requests', function (Blueprint $table): void {
            if (! $this->hasIndex('service_requests', 'sr_service_status_archived')) {
                $table->index(['service', 'status', 'archived_at'], 'sr_service_status_archived');
            }
            if (! $this->hasIndex('service_requests', 'sr_student_service_status')) {
                $table->index(['student_number', 'service', 'status'], 'sr_student_service_status');
            }
        });

        // guidance_appointments – archive / category filter
        Schema::table('guidance_appointments', function (Blueprint $table): void {
            if (! $this->hasIndex('guidance_appointments', 'ga_category_archived')) {
                $table->index(['test_category', 'is_archived'], 'ga_category_archived');
            }
        });

        // admission_applicants – masterlist sort
        Schema::table('admission_applicants', function (Blueprint $table): void {
            if (! $this->hasIndex('admission_applicants', 'aa_cycle_last_name')) {
                $table->index(['admission_cycle_id', 'last_name'], 'aa_cycle_last_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropIndex('sr_service_status_archived');
            $table->dropIndex('sr_student_service_status');
        });

        Schema::table('guidance_appointments', function (Blueprint $table): void {
            $table->dropIndex('ga_category_archived');
        });

        Schema::table('admission_applicants', function (Blueprint $table): void {
            $table->dropIndex('aa_cycle_last_name');
        });
    }

    /** Safe index existence check — avoids duplicate key errors on re-run. */
    private function hasIndex(string $table, string $index): bool
    {
        $indexes = \Illuminate\Support\Facades\DB::select(
            'SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?',
            [$index]
        );
        return count($indexes) > 0;
    }
};
