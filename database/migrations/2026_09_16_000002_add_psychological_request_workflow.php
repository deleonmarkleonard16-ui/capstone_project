<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('gender')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('proof_path')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('venue')->nullable();
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', fn (Blueprint $table) => $table->dropColumn([
            'gender', 'birthdate', 'proof_path', 'scheduled_at', 'venue', 'archived_at',
        ]));
    }
};
