<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->string('origin_course')->nullable();
            $table->string('origin_section', 50)->nullable();
        });

        DB::table('guidance_appointments')->whereNotNull('batch_id')->orWhereNotNull('source_batch_id')
            ->orderBy('guidance_appointment_id')->chunkById(100, function ($appointments) {
                foreach ($appointments as $appointment) {
                    $batch = DB::table('guidance_test_batches')->where('batch_id', $appointment->source_batch_id ?: $appointment->batch_id)->first();
                    if ($batch) DB::table('guidance_appointments')->where('guidance_appointment_id', $appointment->guidance_appointment_id)
                        ->update(['origin_course' => $batch->course, 'origin_section' => $batch->year_section]);
                }
            }, 'guidance_appointment_id');
    }

    public function down(): void
    {
        Schema::table('guidance_appointments', fn (Blueprint $table) => $table->dropColumn(['origin_course', 'origin_section']));
    }
};
