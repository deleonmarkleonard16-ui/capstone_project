<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admission_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_sessions', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('start_time');
            }
            if (!Schema::hasColumn('admission_sessions', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('admission_sessions', 'ended_at')) {
                $table->dropColumn('ended_at');
            }
            if (Schema::hasColumn('admission_sessions', 'started_at')) {
                $table->dropColumn('started_at');
            }
        });
    }
};
