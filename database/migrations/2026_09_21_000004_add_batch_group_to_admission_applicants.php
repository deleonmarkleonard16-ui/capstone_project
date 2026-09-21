<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_applicants', 'batch_group')) {
                $table->string('batch_group', 100)->nullable()->after('admission_cycle_id');
            }
            if (!Schema::hasColumn('admission_applicants', 'session_label')) {
                $table->string('session_label', 100)->nullable()->after('batch_group');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (Schema::hasColumn('admission_applicants', 'batch_group')) {
                $table->dropColumn('batch_group');
            }
            if (Schema::hasColumn('admission_applicants', 'session_label')) {
                $table->dropColumn('session_label');
            }
        });
    }
};
