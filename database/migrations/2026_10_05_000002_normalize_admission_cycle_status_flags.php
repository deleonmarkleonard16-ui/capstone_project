<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalize stale admission_cycles data on Render (or any deployed DB).
 *
 * Problem: Some cycles on the deployed database have mismatched flags, e.g.:
 *   - is_active=1 BUT status='Completed' or 'Archived'
 *   - is_active=0 AND is_archived=0 BUT status='Active'
 *
 * This causes:
 *   - AdmissionCycle::active() to return a stale cycle → sidebar enables buttons
 *   - Gatekeeper to block (cycle found but wrong status) → raw 409 page
 *   - Sidebar buttons appear clickable even when no real Active cycle exists
 *
 * Fix: Align is_active, is_archived, and status to be consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admission_cycles')) {
            return;
        }

        DB::transaction(function () {
            // 1. Fix rows where is_active=1 but status is NOT 'Active' or 'Maintenance'
            //    These are stale rows from failed/interrupted sessions.
            //    Demote them: set is_active=0, is_archived=1, status='Completed'
            DB::table('admission_cycles')
                ->where('is_active', true)
                ->where('is_archived', false)
                ->whereNotIn('status', ['Active', 'Maintenance'])
                ->update([
                    'is_active'   => false,
                    'is_archived' => true,
                    'status'      => 'Completed',
                ]);

            // 2. Fix rows where status='Active' but is_active=0
            //    These are display-label mismatches. Align status to the flag.
            DB::table('admission_cycles')
                ->where('is_active', false)
                ->where('status', 'Active')
                ->update([
                    'status' => 'Completed',
                ]);

            // 3. Fix rows where is_archived=1 but status is still 'Active'
            DB::table('admission_cycles')
                ->where('is_archived', true)
                ->where('status', 'Active')
                ->update([
                    'is_active' => false,
                    'status'    => 'Completed',
                ]);
        });
    }

    public function down(): void
    {
        // Non-destructive normalization — no meaningful rollback
    }
};
