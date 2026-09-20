<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guidance_test_batches', function (Blueprint $table) {
            $table->id('batch_id');
            $table->string('batch_token', 64)->unique();
            $table->string('batch_name');
            $table->string('course');
            $table->enum('test_type', ['Psychological Assessment', 'Personality Test', 'Career Test']);
            $table->string('reason_for_request');
            $table->enum('status', ['Pending Registration', 'In-Progress', 'Completed'])->default('Pending Registration')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->foreign('batch_id')->references('batch_id')->on('guidance_test_batches')->restrictOnDelete();
            $table->enum('attendance_status', ['Pending Scan', 'Ready', 'Absent', 'Completed'])->default('Pending Scan');
            $table->string('student_id_number', 100)->nullable();
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->foreign('source_batch_id')->references('batch_id')->on('guidance_test_batches')->restrictOnDelete();
            $table->unsignedInteger('section_index')->default(0);
            $table->json('section_history')->nullable();
            $table->unique(['batch_id', 'student_id_number']);
        });
    }

    public function down(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->dropUnique(['batch_id', 'student_id_number']);
            $table->dropForeign(['batch_id']);
            $table->dropForeign(['source_batch_id']);
            $table->dropColumn(['batch_id', 'source_batch_id', 'attendance_status', 'student_id_number', 'section_index', 'section_history']);
        });
        Schema::dropIfExists('guidance_test_batches');
    }
};
