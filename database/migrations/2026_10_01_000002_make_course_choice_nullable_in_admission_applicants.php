<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE admission_applicants MODIFY course_choice VARCHAR(100) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE admission_applicants MODIFY second_course_choice VARCHAR(100) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE admission_applicants MODIFY course_choice_1 VARCHAR(100) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE admission_applicants MODIFY course_choice_2 VARCHAR(100) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE admission_applicants MODIFY course_choice VARCHAR(100) NOT NULL");
    }
};
