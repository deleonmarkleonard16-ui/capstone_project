<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['service_requests', 'guidance_appointments'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->mediumBlob('receipt_data')->nullable();
                $table->string('receipt_mime_type', 100)->nullable();
                $table->string('receipt_original_name', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['service_requests', 'guidance_appointments'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn(['receipt_data', 'receipt_mime_type', 'receipt_original_name']);
            });
        }
    }
};
