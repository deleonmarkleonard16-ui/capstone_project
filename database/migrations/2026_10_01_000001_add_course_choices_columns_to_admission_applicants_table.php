<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_applicants', 'course_choice_1')) {
                $table->string('course_choice_1', 100)->nullable()->after('last_name');
            }
            if (!Schema::hasColumn('admission_applicants', 'course_choice_2')) {
                $table->string('course_choice_2', 100)->nullable()->after('course_choice_1');
            }
        });

        // Backfill existing data from course_choice and second_course_choice
        if (Schema::hasColumn('admission_applicants', 'course_choice') && Schema::hasColumn('admission_applicants', 'course_choice_1')) {
            DB::statement("UPDATE admission_applicants SET course_choice_1 = course_choice WHERE (course_choice_1 IS NULL OR course_choice_1 = '') AND (course_choice IS NOT NULL AND course_choice != '')");
        }
        if (Schema::hasColumn('admission_applicants', 'second_course_choice') && Schema::hasColumn('admission_applicants', 'course_choice_2')) {
            DB::statement("UPDATE admission_applicants SET course_choice_2 = second_course_choice WHERE (course_choice_2 IS NULL OR course_choice_2 = '') AND (second_course_choice IS NOT NULL AND second_course_choice != '')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (Schema::hasColumn('admission_applicants', 'course_choice_2')) {
                $table->dropColumn('course_choice_2');
            }
            if (Schema::hasColumn('admission_applicants', 'course_choice_1')) {
                $table->dropColumn('course_choice_1');
            }
        });
    }
};
