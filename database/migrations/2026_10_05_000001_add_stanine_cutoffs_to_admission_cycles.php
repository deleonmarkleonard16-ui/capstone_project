<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add stanine_cutoff_board and stanine_cutoff_non_board to admission_cycles.
 *
 * These columns were referenced in AdmissionCycle model and AdmissionPipelineController
 * but were never included in any previous migration, causing a 500 on cycle creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_cycles', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_cycles', 'stanine_cutoff_board')) {
                $table->unsignedTinyInteger('stanine_cutoff_board')
                      ->default(4)
                      ->after('passing_stanine')
                      ->comment('Board program qualifying stanine cutoff (default 4)');
            }
            if (!Schema::hasColumn('admission_cycles', 'stanine_cutoff_non_board')) {
                $table->unsignedTinyInteger('stanine_cutoff_non_board')
                      ->default(3)
                      ->after('stanine_cutoff_board')
                      ->comment('Non-board program qualifying stanine cutoff (default 3)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_cycles', function (Blueprint $table) {
            $toDrop = [];
            if (Schema::hasColumn('admission_cycles', 'stanine_cutoff_board')) {
                $toDrop[] = 'stanine_cutoff_board';
            }
            if (Schema::hasColumn('admission_cycles', 'stanine_cutoff_non_board')) {
                $toDrop[] = 'stanine_cutoff_non_board';
            }
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
