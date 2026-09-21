<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. admission_cycles table update
        Schema::table('admission_cycles', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_cycles', 'cycle_name')) {
                $table->string('cycle_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('admission_cycles', 'status')) {
                $table->enum('status', ['Draft', 'Active', 'Completed'])->default('Draft')->after('cycle_name');
            }
        });

        // Sync existing cycles
        try {
            DB::statement("UPDATE admission_cycles SET cycle_name = name WHERE cycle_name IS NULL AND name IS NOT NULL");
            DB::statement("UPDATE admission_cycles SET status = 'Active' WHERE is_active = 1");
            DB::statement("UPDATE admission_cycles SET status = 'Completed' WHERE is_archived = 1");
        } catch (\Throwable $e) {
            // SQLite or query fallback
            DB::table('admission_cycles')->whereNull('cycle_name')->update(['cycle_name' => DB::raw('name')]);
            DB::table('admission_cycles')->where('is_active', true)->update(['status' => 'Active']);
            DB::table('admission_cycles')->where('is_archived', true)->update(['status' => 'Completed']);
        }

        // 2. admission_applicants column validation
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_applicants', '4ps_osy_ip_pwd_sp')) {
                $table->string('4ps_osy_ip_pwd_sp', 120)->nullable()->after('sex');
            }
        });

        // Sync 4ps_osy_ip_pwd_sp from special_group if needed
        try {
            DB::statement("UPDATE admission_applicants SET `4ps_osy_ip_pwd_sp` = special_group WHERE `4ps_osy_ip_pwd_sp` IS NULL AND special_group IS NOT NULL");
        } catch (\Throwable $e) {
            // Ignore if already synced or not supported
        }

        // 3. Ensure courses table exists and matches requirements
        if (!Schema::hasTable('courses')) {
            Schema::create('courses', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('admission_applicants', function (Blueprint $table) {
            if (Schema::hasColumn('admission_applicants', '4ps_osy_ip_pwd_sp')) {
                $table->dropColumn('4ps_osy_ip_pwd_sp');
            }
        });

        Schema::table('admission_cycles', function (Blueprint $table) {
            if (Schema::hasColumn('admission_cycles', 'cycle_name')) {
                $table->dropColumn('cycle_name');
            }
            if (Schema::hasColumn('admission_cycles', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
