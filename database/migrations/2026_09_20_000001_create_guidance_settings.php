<?php

use App\Support\CourseCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        foreach (CourseCatalog::OPTIONS as $code => $name) {
            DB::table('courses')->insert(['code' => $code, 'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('guidance_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
            $table->timestamps();
        });
        Schema::create('guidance_request_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('guidance_strike_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guidance_appointment_id')->constrained('guidance_appointments', 'guidance_appointment_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->unsignedInteger('previous_strikes');
            $table->timestamps();
        });
        Schema::table('users', fn (Blueprint $table) => $table->boolean('can_proctor')->default(false));
        Schema::table('service_requests', fn (Blueprint $table) => $table->string('academic_year')->nullable());
        Schema::table('guidance_test_batches', fn (Blueprint $table) => $table->string('academic_year')->nullable());
        DB::table('guidance_settings')->insert(['key' => 'strike_threshold', 'value' => '3', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('guidance_test_batches', fn (Blueprint $table) => $table->dropColumn('academic_year'));
        Schema::table('service_requests', fn (Blueprint $table) => $table->dropColumn('academic_year'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_proctor'));
        Schema::dropIfExists('guidance_request_reasons');
        Schema::dropIfExists('guidance_strike_resets');
        Schema::dropIfExists('guidance_settings');
        Schema::dropIfExists('courses');
    }
};
