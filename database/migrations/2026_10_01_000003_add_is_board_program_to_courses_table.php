<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('courses', 'is_board_program')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->boolean('is_board_program')->default(false)->after('name');
            });
        }

        // Set board programs
        DB::table('courses')
            ->whereIn('code', ['BEED', 'BSED-FIL', 'BSED-SOC', 'BTLEd', 'BSED', 'BTLED'])
            ->update(['is_board_program' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'is_board_program')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('is_board_program');
            });
        }
    }
};
