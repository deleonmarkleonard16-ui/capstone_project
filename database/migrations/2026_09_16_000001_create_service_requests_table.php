<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('service');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('student_status');
            $table->string('student_number');
            $table->string('email');
            $table->string('contact_number');
            $table->string('course');
            $table->text('purpose');
            $table->json('tests')->nullable();
            $table->unsignedSmallInteger('copies')->nullable();
            $table->string('status')->default('pending');
            $table->text('staff_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
