<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admission_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('test_session_id')->nullable()->constrained('test_sessions')->nullOnDelete();
            $table->decimal('exam_score', 8, 2)->default(0);
            $table->decimal('exam_percentage', 8, 2)->default(0);
            $table->decimal('gwa', 8, 2)->nullable();
            $table->decimal('gwa_percentage', 8, 2)->default(0);
            $table->decimal('interview_score', 8, 2)->nullable();
            $table->decimal('interview_percentage', 8, 2)->default(0);
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->string('status')->default('qualified'); // qualified, waitlisted, disqualified
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique('applicant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_evaluations');
    }
};
