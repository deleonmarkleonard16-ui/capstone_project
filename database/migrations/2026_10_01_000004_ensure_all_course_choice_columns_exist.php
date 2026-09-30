<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent migration: ensures all dual-choice + stanine columns exist in
 * admission_applicants regardless of which prior migrations ran on the target
 * database (handles both local XAMPP and remote Railway environments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table): void {
            // ── Dual course-choice aliases ────────────────────────────────
            if (!Schema::hasColumn('admission_applicants', 'second_course_choice')) {
                $table->string('second_course_choice', 100)->nullable()->after('course_choice');
            }
            if (!Schema::hasColumn('admission_applicants', 'course_choice_1')) {
                $table->string('course_choice_1', 100)->nullable()->after('last_name');
            }
            if (!Schema::hasColumn('admission_applicants', 'course_choice_2')) {
                $table->string('course_choice_2', 100)->nullable()->after('course_choice_1');
            }
        });

        // Make all four course-choice columns nullable (no-op if already nullable)
        foreach (['course_choice', 'second_course_choice', 'course_choice_1', 'course_choice_2'] as $col) {
            if (Schema::hasColumn('admission_applicants', $col)) {
                Schema::table('admission_applicants', function (Blueprint $table) use ($col): void {
                    $table->string($col, 100)->nullable()->change();
                });
            }
        }

        // Backfill cross-aliases from existing data
        if (Schema::hasColumn('admission_applicants', 'course_choice') &&
            Schema::hasColumn('admission_applicants', 'course_choice_1')) {
            DB::table('admission_applicants')
                ->whereNull('course_choice_1')
                ->whereNotNull('course_choice')
                ->update(['course_choice_1' => DB::raw('course_choice')]);
        }

        if (Schema::hasColumn('admission_applicants', 'second_course_choice') &&
            Schema::hasColumn('admission_applicants', 'course_choice_2')) {
            DB::table('admission_applicants')
                ->whereNull('course_choice_2')
                ->whereNotNull('second_course_choice')
                ->update(['course_choice_2' => DB::raw('second_course_choice')]);
        }
    }

    public function down(): void
    {
        // Intentionally left empty — retaining the columns is safer than dropping them.
    }
};
