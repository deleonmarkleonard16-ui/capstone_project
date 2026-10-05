<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Legacy production databases created admission_sessions.start_time as TIME.
     * A TIME value discards the date selected in the session form, so convert it
     * to DATETIME before the controller saves another schedule.
     */
    public function up(): void
    {
        if (!Schema::hasTable('admission_sessions') || !Schema::hasColumn('admission_sessions', 'start_time')) {
            return;
        }

        // The hosted production database is MySQL. SQLite is used by the test
        // suite and already creates the column as DATETIME.
        if (DB::getDriverName() !== 'mysql' || Schema::getColumnType('admission_sessions', 'start_time') !== 'time') {
            return;
        }

        // Preserve each legacy session's existing time and use its last update
        // date as the best available date. The old TIME schema never stored a
        // session date, so users should resave historical sessions if needed.
        DB::statement('ALTER TABLE admission_sessions ADD COLUMN start_time_datetime DATETIME NULL AFTER start_time');
        DB::statement('UPDATE admission_sessions SET start_time_datetime = TIMESTAMP(DATE(updated_at), start_time)');
        DB::statement('ALTER TABLE admission_sessions DROP COLUMN start_time, CHANGE COLUMN start_time_datetime start_time DATETIME NOT NULL');
    }

    public function down(): void
    {
        // Lossy downgrade is intentionally omitted: converting back to TIME
        // would discard the schedule date this migration restores.
    }
};
