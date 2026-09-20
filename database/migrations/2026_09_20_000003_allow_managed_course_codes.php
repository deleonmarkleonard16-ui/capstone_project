<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['service_requests', 'guidance_test_batches'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('course', 30)->nullable()->change());
        }
    }

    public function down(): void
    {
        // Preserve codes created by administrators during the lifetime of this schema.
    }
};
