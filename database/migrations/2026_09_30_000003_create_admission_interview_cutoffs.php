<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MySQL may leave the table behind when the original, overlong index name fails.
        if (! Schema::hasTable('admission_interview_cutoffs')) {
            Schema::create('admission_interview_cutoffs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
                $table->string('course_code', 30);
                $table->unsignedInteger('top_limit');
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('admission_interview_cutoffs', ['admission_cycle_id', 'course_code'], 'unique')) {
            Schema::table('admission_interview_cutoffs', function (Blueprint $table) {
                $table->unique(['admission_cycle_id', 'course_code'], 'adm_interview_cycle_course_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_interview_cutoffs');
    }
};
