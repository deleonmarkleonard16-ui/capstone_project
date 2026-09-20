<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scanned_at')->nullable();
            $table->boolean('is_present')->default(false);
            $table->timestamps();

            $table->unique(['test_session_id', 'applicant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_applicants');
    }
};
