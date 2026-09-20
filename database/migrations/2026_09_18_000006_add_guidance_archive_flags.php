<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->index(['is_archived', 'status'], 'guidance_archive_status_index');
        });
        DB::table('guidance_appointments')->where('status', 'Completed')
            ->update(['is_archived' => true, 'archived_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $table->dropIndex('guidance_archive_status_index');
            $table->dropColumn(['is_archived', 'archived_at']);
        });
    }
};
