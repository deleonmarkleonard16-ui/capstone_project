<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('guidance_test_batches') || !Schema::hasColumn('guidance_test_batches', 'status')) {
            return;
        }

        Schema::table('guidance_test_batches', function (Blueprint $table) {
            $table->string('status', 30)->default('Pending Registration')->change();
        });
    }

    public function down(): void
    {
        // Keep the string column so batches already in Under review remain valid.
    }
};
