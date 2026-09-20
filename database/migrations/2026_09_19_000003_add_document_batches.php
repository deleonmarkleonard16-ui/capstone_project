<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guidance_test_batches', function (Blueprint $table) {
            $table->string('test_type')->nullable()->change();
            $table->enum('module_type', ['Psychological Assessment', 'Personality Test', 'Career Test', 'Good Moral', 'Exit Form'])->nullable()->index();
        });
        DB::table('guidance_test_batches')->whereNull('module_type')->update(['module_type' => DB::raw('test_type')]);
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->enum('attendance_status', ['Pending Scan', 'Ready', 'Absent', 'Approved', 'Completed', 'Claimed/Completed'])->default('Pending Scan')->change();
        });
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->constrained('guidance_test_batches', 'batch_id')->restrictOnDelete();
            $table->index(['service', 'batch_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('guidance_test_batches')->whereNull('test_type')->exists()) {
            throw new RuntimeException('Remove document batches before rolling back this migration.');
        }
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex(['service', 'batch_id', 'archived_at']);
            $table->dropConstrainedForeignId('batch_id');
        });
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->enum('attendance_status', ['Pending Scan', 'Ready', 'Absent', 'Completed'])->default('Pending Scan')->change();
        });
        Schema::table('guidance_test_batches', function (Blueprint $table) {
            $table->dropColumn('module_type');
            $table->enum('test_type', ['Psychological Assessment', 'Personality Test', 'Career Test'])->change();
        });
    }
};
