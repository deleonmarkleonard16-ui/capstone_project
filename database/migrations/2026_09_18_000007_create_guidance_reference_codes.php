<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One namespace for request and appointment codes; old references remain valid.
        Schema::create('guidance_reference_codes', function (Blueprint $table) {
            $table->string('code', 6)->primary();
            $table->timestamp('created_at');
        });
        foreach (['service_requests' => 'reference', 'guidance_appointments' => 'request_code'] as $table => $column) {
            DB::table($table)->where($column, 'like', 'G-%')->orderBy($column)->select($column)
                ->chunk(200, function ($rows) use ($column) {
                    foreach ($rows as $row) {
                        if (preg_match('/^G-[A-Z0-9]{4}$/D', $row->$column)) {
                            DB::table('guidance_reference_codes')->insertOrIgnore(['code' => $row->$column, 'created_at' => now()]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_reference_codes');
    }
};
