<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidance_appointments', function (Blueprint $table) {
            $table->id('guidance_appointment_id');
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->string('request_code', 64)->unique();
            $table->string('test_type', 30);
            $table->string('student_status', 20);
            $table->enum('status', ['Pending', 'Approved', 'In-Progress', 'Completed'])->default('Pending')->index();
            $table->dateTime('appointment_at')->nullable();
            $table->string('payment_slip_path');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('guidance_test_qr_codes', function (Blueprint $table) {
            $table->id('guidance_test_qr_code_id');
            $table->unsignedBigInteger('guidance_appointment_id')->unique();
            $table->foreign('guidance_appointment_id')->references('guidance_appointment_id')->on('guidance_appointments')->restrictOnDelete();
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('guidance_test_responses', function (Blueprint $table) {
            $table->id('guidance_test_response_id');
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->unsignedBigInteger('guidance_appointment_id')->unique();
            $table->foreign('guidance_appointment_id')->references('guidance_appointment_id')->on('guidance_appointments')->restrictOnDelete();
            $table->json('answers');
            $table->json('score_summary');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_test_responses');
        Schema::dropIfExists('guidance_test_qr_codes');
        Schema::dropIfExists('guidance_appointments');
    }
};
