<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guidance_request_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('module', 32);
            $table->timestamps();
            $table->unique(['service_request_id', 'module']);
        });
        Schema::create('guidance_notification_reads', function (Blueprint $table) {
            $table->foreignId('notification_id')->constrained('guidance_request_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->primary(['notification_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_notification_reads');
        Schema::dropIfExists('guidance_request_notifications');
    }
};
