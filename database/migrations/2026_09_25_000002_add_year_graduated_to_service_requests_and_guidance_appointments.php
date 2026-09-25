<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('service_requests') && !Schema::hasColumn('service_requests', 'year_graduated')) {
            Schema::table('service_requests', function (Blueprint $table) {
                $table->string('year_graduated', 10)->nullable()->after('student_number');
            });
        }

        if (Schema::hasTable('guidance_appointments') && !Schema::hasColumn('guidance_appointments', 'year_graduated')) {
            Schema::table('guidance_appointments', function (Blueprint $table) {
                $table->string('year_graduated', 10)->nullable()->after('student_id_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_requests') && Schema::hasColumn('service_requests', 'year_graduated')) {
            Schema::table('service_requests', function (Blueprint $table) {
                $table->dropColumn('year_graduated');
            });
        }

        if (Schema::hasTable('guidance_appointments') && Schema::hasColumn('guidance_appointments', 'year_graduated')) {
            Schema::table('guidance_appointments', function (Blueprint $table) {
                $table->dropColumn('year_graduated');
            });
        }
    }
};
