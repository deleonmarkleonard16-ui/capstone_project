<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admission_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('academic_year');
            $table->string('semester')->default('1st Semester');
            $table->boolean('is_active')->default(true);
            $table->decimal('exam_weight', 5, 2)->default(60.00);
            $table->decimal('gwa_weight', 5, 2)->default(20.00);
            $table->decimal('interview_weight', 5, 2)->default(20.00);
            $table->decimal('passing_rate', 5, 2)->default(75.00);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_cycles');
    }
};
