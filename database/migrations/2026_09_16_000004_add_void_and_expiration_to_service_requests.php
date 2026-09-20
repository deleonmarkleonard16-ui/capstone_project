<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('status');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            \Illuminate\Support\Facades\DB::table('service_requests')->whereNull('expires_at')->update([
                'expires_at' => \Illuminate\Support\Facades\DB::raw("datetime(created_at, '+5 days')"),
            ]);
        } else {
            \Illuminate\Support\Facades\DB::table('service_requests')->whereNull('expires_at')->update([
                'expires_at' => \Illuminate\Support\Facades\DB::raw('DATE_ADD(created_at, INTERVAL 5 DAY)'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
