<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['service_requests' => 'id', 'guidance_appointments' => 'guidance_appointment_id'] as $tableName => $key) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'batch_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($key) {
                    $table->foreignId('batch_id')->nullable()->after($key)
                        ->constrained('guidance_test_batches', 'batch_id')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Repair migrations must not remove batch links created by earlier migrations.
    }
};
