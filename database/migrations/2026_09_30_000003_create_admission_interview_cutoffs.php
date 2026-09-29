<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admission_interview_cutoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('course_code', 30);
            $table->unsignedInteger('top_limit');
            $table->timestamps();
            $table->unique(['admission_cycle_id', 'course_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_interview_cutoffs');
    }
};
