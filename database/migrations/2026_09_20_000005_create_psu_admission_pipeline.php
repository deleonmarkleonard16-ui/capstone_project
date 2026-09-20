<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admission_cycles', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false);
            $table->unsignedTinyInteger('passing_stanine')->default(4);
        });
        Schema::create('admission_course_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('course_code', 30);
            $table->unsignedInteger('seats');
            $table->timestamps();
            $table->unique(['admission_cycle_id', 'course_code']);
        });
        Schema::create('admission_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->restrictOnDelete();
            $table->string('application_number')->unique();
            $table->string('student_id')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('course_choice', 30);
            $table->string('sex', 20)->nullable();
            $table->string('special_group', 120)->nullable();
            $table->string('cmfl', 120)->nullable();
            $table->decimal('gwa', 5, 2)->nullable();
            $table->decimal('interview_score', 5, 2)->nullable();
            $table->decimal('exam_score', 5, 2)->nullable();
            $table->unsignedTinyInteger('stanine_score')->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->string('qualification_status', 20)->default('Pending');
            $table->string('exam_token', 64)->unique()->nullable();
            $table->json('answers')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedTinyInteger('strike_count')->default(0);
            $table->timestamps();
            $table->index(['admission_cycle_id', 'course_choice']);
        });
        Schema::create('admission_answer_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('item_number');
            $table->string('correct_answer', 1);
            $table->timestamps();
            $table->unique(['admission_cycle_id', 'item_number']);
        });
        Schema::create('admission_security_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->foreignId('applicant_id')->constrained('admission_applicants')->cascadeOnDelete();
            $table->string('incident_type', 80);
            $table->unsignedTinyInteger('strike_number');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_security_logs');
        Schema::dropIfExists('admission_answer_keys');
        Schema::dropIfExists('admission_applicants');
        Schema::dropIfExists('admission_course_quotas');
        Schema::table('admission_cycles', fn (Blueprint $table) => $table->dropColumn(['is_archived', 'passing_stanine']));
    }
};
