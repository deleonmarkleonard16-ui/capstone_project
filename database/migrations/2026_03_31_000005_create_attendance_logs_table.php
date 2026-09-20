<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scanned_at');
            $table->string('verified_name');
            $table->string('verified_gender', 20);
            $table->string('verified_application_number');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['test_session_id', 'applicant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
