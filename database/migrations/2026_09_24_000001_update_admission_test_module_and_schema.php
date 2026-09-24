<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Ensure admission_cycles has status capable of 'Active', 'Archived', 'Maintenance'
        Schema::table('admission_cycles', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_cycles', 'cycle_name')) {
                $table->string('cycle_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('admission_cycles', 'academic_year')) {
                $table->string('academic_year', 50)->default('2026-2027')->after('cycle_name');
            }
            if (!Schema::hasColumn('admission_cycles', 'status')) {
                $table->string('status', 30)->default('Draft')->after('cycle_name');
            }
        });

        // 2. Create admission_sessions table as specified in Requirement 8
        if (!Schema::hasTable('admission_sessions')) {
            Schema::create('admission_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_cycle_id')->constrained('admission_cycles')->cascadeOnDelete();
                $table->string('session_name');
                $table->unsignedInteger('start_number');
                $table->unsignedInteger('end_number');
                $table->string('room')->nullable();
                $table->date('exam_date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->unsignedInteger('duration_minutes')->default(40);
                $table->string('qr_token', 64)->unique()->nullable();
                $table->string('status', 30)->default('Active');
                $table->timestamps();
            });
        }

        // 3. Link admission_applicants to admission_sessions
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_applicants', 'admission_session_id')) {
                $table->foreignId('admission_session_id')->nullable()->after('admission_cycle_id')
                    ->constrained('admission_sessions')->nullOnDelete();
            }
            if (!Schema::hasColumn('admission_applicants', '4ps_osy_ip_pwd_sp')) {
                $table->string('4ps_osy_ip_pwd_sp', 120)->nullable()->after('sex');
            }
        });

        // 4. Ensure guidance_test_security_logs matches Requirement 8
        if (Schema::hasTable('guidance_test_security_logs')) {
            Schema::table('guidance_test_security_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('guidance_test_security_logs', 'applicant_id')) {
                    $table->foreignId('applicant_id')->nullable()->after('log_id')
                        ->constrained('admission_applicants')->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (Schema::hasColumn('admission_applicants', 'admission_session_id')) {
                $table->dropConstrainedForeignId('admission_session_id');
            }
        });

        Schema::dropIfExists('admission_sessions');
    }
};
