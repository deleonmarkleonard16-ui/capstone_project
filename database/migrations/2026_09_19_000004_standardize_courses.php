<?php

use App\Support\CourseCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['service_requests', 'guidance_test_batches'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('course')->nullable()->change();
                $blueprint->string('legacy_course')->nullable();
            });
            $key = $table === 'service_requests' ? 'id' : 'batch_id';
            DB::table($table)->orderBy($key)
                ->chunk(100, function ($rows) use ($table) {
                    $key = $table === 'service_requests' ? 'id' : 'batch_id';
                    foreach ($rows as $row) {
                        $code = CourseCatalog::normalizeLegacy($row->course);
                        DB::table($table)->where($key, $row->$key)->update([
                            'course' => $code,
                            'legacy_course' => $code ? null : $row->course,
                        ]);
                    }
                });
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->enum('course', array_keys(CourseCatalog::OPTIONS))->nullable()->change();
            });
        }
        Schema::table('guidance_test_batches', function (Blueprint $table) {
            $table->string('year_section', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guidance_test_batches', function (Blueprint $table) {
            $table->dropColumn('year_section');
        });
        foreach (['service_requests', 'guidance_test_batches'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('course')->nullable()->change();
            });
            DB::table($table)->whereNotNull('legacy_course')->update(['course' => DB::raw('legacy_course')]);
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('legacy_course');
            });
        }
    }
};
