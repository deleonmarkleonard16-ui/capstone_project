<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table): void {
            foreach (['course_choice', 'second_course_choice', 'course_choice_1', 'course_choice_2'] as $column) {
                if (Schema::hasColumn('admission_applicants', $column)) {
                    $table->string($column, 100)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table): void {
            foreach (['course_choice', 'second_course_choice', 'course_choice_1', 'course_choice_2'] as $column) {
                if (Schema::hasColumn('admission_applicants', $column)) {
                    $table->string($column, 100)->nullable(false)->change();
                }
            }
        });
    }
};
