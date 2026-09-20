<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('qr_token')->unique();
            $table->text('qr_code_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(40);
            $table->string('room')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_sessions');
    }
};
