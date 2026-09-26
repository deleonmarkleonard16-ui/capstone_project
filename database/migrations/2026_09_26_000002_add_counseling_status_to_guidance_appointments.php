<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('guidance_appointments', 'counseling_status')) {
                $table->string('counseling_status', 50)->default('Pending Review')->nullable()->after('status');
            }
            if (!Schema::hasColumn('guidance_appointments', 'counseling_notes')) {
                $table->text('counseling_notes')->nullable()->after('counseling_status');
            }
            if (!Schema::hasColumn('guidance_appointments', 'counseling_updated_at')) {
                $table->timestamp('counseling_updated_at')->nullable()->after('counseling_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('guidance_appointments', 'counseling_status')) $cols[] = 'counseling_status';
            if (Schema::hasColumn('guidance_appointments', 'counseling_notes')) $cols[] = 'counseling_notes';
            if (Schema::hasColumn('guidance_appointments', 'counseling_updated_at')) $cols[] = 'counseling_updated_at';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
