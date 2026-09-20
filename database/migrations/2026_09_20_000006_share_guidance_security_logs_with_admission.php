<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guidance_test_security_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('guidance_appointment_id')->nullable()->change();
            $table->foreignId('applicant_id')->nullable()->constrained('admission_applicants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guidance_test_security_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applicant_id');
            $table->unsignedBigInteger('guidance_appointment_id')->nullable(false)->change();
        });
    }
};
