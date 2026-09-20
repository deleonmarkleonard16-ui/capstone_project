<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guidance_test_security_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->unsignedBigInteger('guidance_appointment_id');
            $table->foreign('guidance_appointment_id')->references('guidance_appointment_id')->on('guidance_appointments')->cascadeOnDelete();
            $table->string('incident_type', 100);
            $table->unsignedInteger('strike_number');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_test_security_logs');
    }
};
