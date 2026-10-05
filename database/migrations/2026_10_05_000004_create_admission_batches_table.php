<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained('admission_cycles')->cascadeOnDelete();
            $table->string('batch_name', 120);
            $table->date('batch_date')->nullable();
            $table->string('room', 120)->nullable();
            $table->timestamps();
            $table->unique(['admission_cycle_id', 'batch_name']);
        });

        Schema::table('admission_sessions', function (Blueprint $table) {
            $table->foreignId('admission_batch_id')->nullable()->constrained('admission_batches')->nullOnDelete();
        });

        // Keep all existing schedules and applicant assignments intact. Only the
        // new grouping foreign key is populated for old session records.
        $now = now();
        foreach (DB::table('admission_cycles')->select('id')->get() as $cycle) {
            $batchId = DB::table('admission_batches')->insertGetId([
                'admission_cycle_id' => $cycle->id,
                'batch_name' => 'Existing Sessions',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('admission_sessions')
                ->where('admission_cycle_id', $cycle->id)
                ->whereNull('admission_batch_id')
                ->update(['admission_batch_id' => $batchId]);
        }
    }

    public function down(): void
    {
        Schema::table('admission_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admission_batch_id');
        });
        Schema::dropIfExists('admission_batches');
    }
};
