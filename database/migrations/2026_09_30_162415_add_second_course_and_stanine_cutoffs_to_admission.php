<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * This migration was originally a stub with an incorrect table name ('admission').
     * The stanine cutoff columns (stanine_cutoff_board, stanine_cutoff_non_board)
     * are now properly added in 2026_10_05_000001_add_stanine_cutoffs_to_admission_cycles.php.
     * This file is kept as a no-op to preserve migration history and ordering.
     */
    public function up(): void
    {
        // No-op: see 2026_10_05_000001_add_stanine_cutoffs_to_admission_cycles.php
    }

    public function down(): void
    {
        // No-op
    }
};
