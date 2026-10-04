<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('guidance_appointments', 'status')) {
            DB::statement("ALTER TABLE guidance_appointments MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending Payment'");
        }

        Schema::table('guidance_appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('guidance_appointments', 'remarks')) {
                $table->text('remarks')->nullable()->after('status');
            }
            if (!Schema::hasColumn('guidance_appointments', 'recommendations')) {
                $table->text('recommendations')->nullable()->after('remarks');
            }
            if (!Schema::hasColumn('guidance_appointments', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('recommendations');
            }
            if (!Schema::hasColumn('guidance_appointments', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guidance_appointments', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('guidance_appointments', 'remarks')) $cols[] = 'remarks';
            if (Schema::hasColumn('guidance_appointments', 'recommendations')) $cols[] = 'recommendations';
            if (Schema::hasColumn('guidance_appointments', 'reviewed_by')) $cols[] = 'reviewed_by';
            if (Schema::hasColumn('guidance_appointments', 'reviewed_at')) $cols[] = 'reviewed_at';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
