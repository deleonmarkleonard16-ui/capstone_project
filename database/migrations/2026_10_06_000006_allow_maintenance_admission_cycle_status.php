<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The original lifecycle migration created `status` as an enum that did
     * not include Maintenance. Change it to a string so every status declared
     * by AdmissionCycle can be stored on SQLite, MySQL, and production.
     */
    public function up(): void
    {
        if (!Schema::hasTable('admission_cycles') || !Schema::hasColumn('admission_cycles', 'status')) {
            return;
        }

        Schema::table('admission_cycles', function (Blueprint $table) {
            $table->string('status', 30)->default('Draft')->change();
        });
    }

    public function down(): void
    {
        // Keep the string column on rollback. Converting it back could discard
        // valid Maintenance or Archived lifecycle records.
    }
};
