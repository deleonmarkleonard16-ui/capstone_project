<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['service_requests', 'guidance_appointments'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('receipt_mime_type', 100)->nullable();
                $table->string('receipt_original_name', 255)->nullable();
            });
            // Laravel's schema builder only exposes BLOB (64 KB), while uploads
            // are allowed up to 5 MB. MySQL MEDIUMBLOB supports up to 16 MB.
            DB::statement("ALTER TABLE `{$table}` ADD `receipt_data` MEDIUMBLOB NULL AFTER `receipt_original_name`");
        }
    }

    public function down(): void
    {
        foreach (['service_requests', 'guidance_appointments'] as $table) {
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN `receipt_data`");
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn(['receipt_mime_type', 'receipt_original_name']);
            });
        }
    }
};
