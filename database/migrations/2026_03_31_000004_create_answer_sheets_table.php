<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_locked')->default(false);

            for ($index = 1; $index <= 80; $index++) {
                $table->string("q{$index}", 1)->nullable();
            }

            $table->timestamps();

            $table->unique(['test_session_id', 'applicant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_sheets');
    }
};
