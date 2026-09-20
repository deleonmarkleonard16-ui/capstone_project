<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill test_types for existing guidance_appointments that have NULL test_types.
     * Psychological → ['dass21','phq9','gad7'], Personality → ['bfpi'], Career → ['career']
     */
    public function up(): void
    {
        $map = [
            'psychological' => json_encode(['dass21', 'phq9', 'gad7']),
            'personality'   => json_encode(['bfpi']),
            'career'        => json_encode(['career']),
        ];

        foreach ($map as $category => $types) {
            DB::table('guidance_appointments')
                ->where('test_category', $category)
                ->whereNull('test_types')
                ->update(['test_types' => $types]);
        }
    }

    public function down(): void
    {
        // Reversing a backfill would be data-destructive; leave as-is.
    }
};
