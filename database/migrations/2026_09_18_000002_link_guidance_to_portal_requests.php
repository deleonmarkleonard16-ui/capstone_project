<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->string('payment_slip_path')->nullable()->change();
            $table->foreignId('service_request_id')->nullable()->constrained('service_requests')->restrictOnDelete();
            $table->string('test_category', 30)->nullable();
            $table->unique(['service_request_id', 'test_category'], 'guidance_request_category_unique');
        });
    }

    public function down(): void
    {
        DB::table('guidance_appointments')->whereNull('payment_slip_path')->update(['payment_slip_path' => '']);
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->string('payment_slip_path')->nullable(false)->change();
            $table->dropUnique('guidance_request_category_unique');
            $table->dropConstrainedForeignId('service_request_id');
            $table->dropColumn('test_category');
        });
    }
};
