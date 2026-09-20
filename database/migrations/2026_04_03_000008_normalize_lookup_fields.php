<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('genders', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('applicant_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('test_session_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();

        DB::table('roles')->insert([
            ['slug' => 'admin', 'name' => 'Admin', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'staff', 'name' => 'Staff', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('genders')->insert([
            ['slug' => 'male', 'name' => 'Male', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'female', 'name' => 'Female', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('applicant_statuses')->insert([
            ['slug' => 'pending', 'name' => 'Pending', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'approved', 'name' => 'Approved', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'rejected', 'name' => 'Rejected', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('test_session_statuses')->insert([
            ['slug' => 'draft', 'name' => 'Draft', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'scheduled', 'name' => 'Scheduled', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'in_progress', 'name' => 'In Progress', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'completed', 'name' => 'Completed', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->foreignId('gender_id')->nullable()->after('last_name')->constrained('genders');
            $table->foreignId('applicant_status_id')->nullable()->after('contact_number')->constrained('applicant_statuses');
        });

        Schema::table('test_sessions', function (Blueprint $table) {
            $table->foreignId('test_session_status_id')->nullable()->after('room')->constrained('test_session_statuses');
        });

        $this->backfillForeignKeysFromLegacyColumns();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['gender', 'status']);
        });

        Schema::table('test_sessions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('password');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('last_name');
            $table->string('status')->nullable()->after('contact_number');
        });

        Schema::table('test_sessions', function (Blueprint $table) {
            $table->string('status')->nullable()->after('room');
        });

        $this->backfillLegacyColumnsFromForeignKeys();

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gender_id');
            $table->dropConstrainedForeignId('applicant_status_id');
        });

        Schema::table('test_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('test_session_status_id');
        });

        Schema::dropIfExists('test_session_statuses');
        Schema::dropIfExists('applicant_statuses');
        Schema::dropIfExists('genders');
        Schema::dropIfExists('roles');
    }

    private function backfillForeignKeysFromLegacyColumns(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $genderIds = DB::table('genders')->pluck('id', 'slug');
        $applicantStatusIds = DB::table('applicant_statuses')->pluck('id', 'slug');
        $testSessionStatusIds = DB::table('test_session_statuses')->pluck('id', 'slug');

        DB::table('users')->get(['id', 'role'])->each(function (object $user) use ($roleIds): void {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['role_id' => $roleIds[strtolower((string) $user->role)] ?? null]);
        });

        DB::table('applicants')->get(['id', 'gender', 'status'])->each(function (object $applicant) use ($genderIds, $applicantStatusIds): void {
            DB::table('applicants')
                ->where('id', $applicant->id)
                ->update([
                    'gender_id' => $genderIds[strtolower((string) $applicant->gender)] ?? null,
                    'applicant_status_id' => $applicantStatusIds[strtolower((string) $applicant->status)] ?? null,
                ]);
        });

        DB::table('test_sessions')->get(['id', 'status'])->each(function (object $session) use ($testSessionStatusIds): void {
            DB::table('test_sessions')
                ->where('id', $session->id)
                ->update([
                    'test_session_status_id' => $testSessionStatusIds[strtolower((string) $session->status)] ?? null,
                ]);
        });
    }

    private function backfillLegacyColumnsFromForeignKeys(): void
    {
        DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->update(['users.role' => DB::raw('LOWER(roles.slug)')]);

        DB::table('applicants')
            ->join('genders', 'genders.id', '=', 'applicants.gender_id')
            ->update(['applicants.gender' => DB::raw('genders.name')]);

        DB::table('applicants')
            ->join('applicant_statuses', 'applicant_statuses.id', '=', 'applicants.applicant_status_id')
            ->update(['applicants.status' => DB::raw('applicant_statuses.slug')]);

        DB::table('test_sessions')
            ->join('test_session_statuses', 'test_session_statuses.id', '=', 'test_sessions.test_session_status_id')
            ->update(['test_sessions.status' => DB::raw('test_session_statuses.slug')]);
    }
};
