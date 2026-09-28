<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guidance_test_security_logs', function (Blueprint $table) {
            $table->string('student_id', 100)->nullable()->after('guidance_appointment_id');
            $table->string('course_program', 100)->nullable()->after('student_id');
            $table->string('current_test_taking', 150)->nullable()->after('course_program');
        });
    }

    public function down(): void
    {
        Schema::table('guidance_test_security_logs', function (Blueprint $table) {
            $table->dropColumn(['student_id', 'course_program', 'current_test_taking']);
        });
    }
};
