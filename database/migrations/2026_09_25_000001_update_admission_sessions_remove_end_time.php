<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('admission_sessions')) {
            Schema::table('admission_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('admission_sessions', 'end_time')) {
                    $table->dropColumn('end_time');
                }
                if (Schema::hasColumn('admission_sessions', 'exam_date')) {
                    $table->dropColumn('exam_date');
                }
                if (Schema::hasColumn('admission_sessions', 'duration_minutes')) {
                    $table->dropColumn('duration_minutes');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admission_sessions')) {
            Schema::table('admission_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('admission_sessions', 'end_time')) {
                    $table->time('end_time')->nullable()->after('start_time');
                }
                if (!Schema::hasColumn('admission_sessions', 'exam_date')) {
                    $table->date('exam_date')->nullable()->after('room');
                }
                if (!Schema::hasColumn('admission_sessions', 'duration_minutes')) {
                    $table->unsignedInteger('duration_minutes')->default(40)->after('end_time');
                }
            });
        }
    }
};
