<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_applicants', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('admission_applicants', 'attendance_status')) {
                $table->string('attendance_status', 30)->default('Absent')->after('checked_in_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (Schema::hasColumn('admission_applicants', 'attendance_status')) {
                $table->dropColumn('attendance_status');
            }
            if (Schema::hasColumn('admission_applicants', 'checked_in_at')) {
                $table->dropColumn('checked_in_at');
            }
        });
    }
};
