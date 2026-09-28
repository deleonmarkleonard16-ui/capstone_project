<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('admission_cycles')
            ->where(function ($query) {
                $query->where('is_archived', true)
                    ->orWhereIn('status', ['Completed', 'Archived']);
            })
            ->update([
                'status' => 'Completed',
                'is_active' => false,
                'is_archived' => true,
            ]);
    }

    public function down(): void
    {
        // Historical archive state cannot be safely inferred in reverse.
    }
};
