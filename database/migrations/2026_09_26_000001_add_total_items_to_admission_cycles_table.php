<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admission_cycles', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_cycles', 'total_items')) {
                $table->unsignedSmallInteger('total_items')->default(80)->after('passing_stanine');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_cycles', function (Blueprint $table) {
            if (Schema::hasColumn('admission_cycles', 'total_items')) {
                $table->dropColumn('total_items');
            }
        });
    }
};
