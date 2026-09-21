<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->string('or_number', 50)->nullable()->after('payment_slip_path');
            $table->date('or_date')->nullable()->after('or_number');
        });
    }

    public function down(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->dropColumn(['or_number', 'or_date']);
        });
    }
};
