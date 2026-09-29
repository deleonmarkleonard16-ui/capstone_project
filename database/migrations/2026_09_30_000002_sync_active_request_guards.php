<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const REQUEST_STATUSES = ['pending', 'receipt-uploaded', 'proof_review', 'approved', 'processing', 'in-progress', 'ready', 'scheduled'];
    private const APPOINTMENT_STATUSES = ['Pending Payment', 'Receipt Uploaded', 'Approved', 'In-Progress'];

    public function up(): void
    {
        // A failed MySQL CREATE INDEX leaves earlier ALTER TABLE statements committed.
        // Every step must be safe to run again on the next deployment.
        foreach (['service_requests', 'guidance_appointments'] as $tableName) {
            if (!Schema::hasColumn($tableName, 'active_guard_exempt')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->boolean('active_guard_exempt')->default(false));
            }
        }

        $this->exemptHistoricalDuplicates('service_requests', 'id', ['student_number', 'service'], self::REQUEST_STATUSES, 'archived_at', null);
        $this->exemptHistoricalDuplicates('guidance_appointments', 'guidance_appointment_id', ['student_id_number', 'test_category'], self::APPOINTMENT_STATUSES, 'archived_at', 'is_archived');

        if (DB::getDriverName() !== 'mysql') return;

        if (Schema::hasIndex('service_requests', 'idx_active_request_per_student')) {
            DB::statement('DROP INDEX idx_active_request_per_student ON service_requests');
        }
        DB::statement("ALTER TABLE service_requests MODIFY COLUMN active_guard CHAR(1) AS
            (CASE WHEN active_guard_exempt = 0 AND NULLIF(TRIM(student_number), '') IS NOT NULL
                AND archived_at IS NULL AND status IN
                ('pending','receipt-uploaded','proof_review','approved','processing','in-progress','ready','scheduled')
                THEN '1' ELSE NULL END) STORED");
        DB::statement('CREATE UNIQUE INDEX idx_active_request_per_student ON service_requests (student_number, service, active_guard)');

        if (Schema::hasIndex('guidance_appointments', 'idx_active_appointment_per_student')) {
            DB::statement('DROP INDEX idx_active_appointment_per_student ON guidance_appointments');
        }
        DB::statement("ALTER TABLE guidance_appointments MODIFY COLUMN active_guard CHAR(1) AS
            (CASE WHEN active_guard_exempt = 0 AND NULLIF(TRIM(student_id_number), '') IS NOT NULL
                AND is_archived = 0 AND archived_at IS NULL
                AND status IN ('Pending Payment','Receipt Uploaded','Approved','In-Progress')
                THEN '1' ELSE NULL END) STORED");
        DB::statement('CREATE UNIQUE INDEX idx_active_appointment_per_student ON guidance_appointments (student_id_number, test_category, active_guard)');
    }

    private function exemptHistoricalDuplicates(string $table, string $key, array $identity, array $statuses, string $archiveColumn, ?string $archiveFlag): void
    {
        $query = DB::table($table)->whereIn('status', $statuses)->whereNull($archiveColumn)
            ->whereNotNull($identity[0])->where($identity[0], '!=', '')->where('active_guard_exempt', false);
        if ($archiveFlag !== null) $query->where($archiveFlag, false);

        $groups = (clone $query)->select($identity)->selectRaw('MIN('.$key.') AS keeper_id, COUNT(*) AS request_count')
            ->groupBy($identity)->havingRaw('COUNT(*) > 1')->get();
        foreach ($groups as $group) {
            $duplicates = DB::table($table)->whereIn('status', $statuses)->whereNull($archiveColumn)
                ->where('active_guard_exempt', false)->where($key, '!=', $group->keeper_id);
            if ($archiveFlag !== null) $duplicates->where($archiveFlag, false);
            foreach ($identity as $column) $duplicates->where($column, $group->$column);
            $duplicates->update(['active_guard_exempt' => true]);
        }
    }

    public function down(): void
    {
        // Keep the corrected guards in place when rolling back later migrations.
    }
};
