<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->unsignedInteger('strike_count')->default(0);
            $table->timestamp('terminated_at')->nullable();
            $table->foreignId('terminated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('termination_reason', 500)->nullable();
        });
        Schema::create('guidance_security_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guidance_appointment_id');
            $table->foreign('guidance_appointment_id')->references('guidance_appointment_id')->on('guidance_appointments')->restrictOnDelete();
            $table->uuid('event_id');
            $table->string('incident_type', 40);
            $table->unsignedInteger('strike_number');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['guidance_appointment_id', 'event_id'], 'guidance_incident_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_security_incidents');
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->dropForeign(['terminated_by']);
            $table->dropColumn(['strike_count', 'terminated_at', 'terminated_by', 'termination_reason']);
        });
    }
};
