<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('or_number', 100)->nullable();
            $table->date('or_date')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->index(['service', 'archived_at', 'status'], 'document_queue_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex('document_queue_lookup');
            $table->dropColumn(['or_number', 'or_date', 'claimed_at']);
        });
    }
};
