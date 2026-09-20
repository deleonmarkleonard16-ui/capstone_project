<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_requests')
            ->whereIn('service', ['testing', 'good-moral', 'exit-form'])
            ->where('status', 'pending')
            ->whereNull('archived_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->update(['status' => 'approved', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Preserve approvals: they cannot be distinguished from staff approvals.
    }
};
