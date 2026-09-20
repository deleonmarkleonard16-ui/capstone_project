<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('contact_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Nullable columns preserve requests submitted without contact details.
    }
};
