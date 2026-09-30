<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('admission_applicants', 'course_choice_1')) {
            Schema::table('admission_applicants', function (Blueprint $table): void {
                $table->string('course_choice_1', 100)->nullable();
            });
        }

        if (!Schema::hasColumn('admission_applicants', 'course_choice_2')) {
            Schema::table('admission_applicants', function (Blueprint $table): void {
                $table->string('course_choice_2', 100)->nullable();
            });
        }

        foreach (['course_choice', 'second_course_choice', 'course_choice_1', 'course_choice_2'] as $column) {
            if (Schema::hasColumn('admission_applicants', $column)) {
                Schema::table('admission_applicants', function (Blueprint $table) use ($column): void {
                    $table->string($column, 100)->nullable()->change();
                });
            }
        }

        if (Schema::hasColumn('admission_applicants', 'course_choice')) {
            DB::table('admission_applicants')
                ->whereNull('course_choice_1')
                ->whereNotNull('course_choice')
                ->update(['course_choice_1' => DB::raw('course_choice')]);
        }

        if (Schema::hasColumn('admission_applicants', 'second_course_choice')) {
            DB::table('admission_applicants')
                ->whereNull('course_choice_2')
                ->whereNotNull('second_course_choice')
                ->update(['course_choice_2' => DB::raw('second_course_choice')]);
        }
    }

    public function down(): void
    {
        // Keep the repaired columns and nullable choices to avoid breaking saved data.
    }
};
