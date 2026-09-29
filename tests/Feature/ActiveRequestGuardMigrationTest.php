<?php

namespace Tests\Feature;

use App\Models\Applicant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveRequestGuardMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_active_duplicates_are_preserved_and_exempted_idempotently(): void
    {
        $now = now();
        foreach (['FIRST', 'SECOND', 'OTHER SERVICE'] as $reference) {
            DB::table('service_requests')->insert([
                'reference' => $reference,
                'service' => $reference === 'OTHER SERVICE' ? 'good-moral' : 'testing',
                'first_name' => 'ANA', 'last_name' => 'CRUZ',
                'student_status' => 'student', 'student_number' => '23-SC-4170',
                'course' => 'BSIT', 'purpose' => 'OJT', 'status' => 'pending',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $applicant = Applicant::create([
            'application_number' => 'GT-MIGRATION-TEST', 'first_name' => 'ANA',
            'last_name' => 'CRUZ', 'status' => 'pending',
        ]);
        foreach (['APPOINTMENT-1', 'APPOINTMENT-2', 'OTHER CATEGORY'] as $code) {
            DB::table('guidance_appointments')->insert([
                'applicant_id' => $applicant->id, 'request_code' => $code,
                'test_type' => 'dass21', 'student_status' => 'student',
                'student_id_number' => '23-SC-4170',
                'test_category' => $code === 'OTHER CATEGORY' ? 'career' : 'psychological',
                'status' => 'Pending Payment', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $migration = require database_path('migrations/2026_09_30_000002_sync_active_request_guards.php');
        $migration->up();
        $migration->up();

        $requests = DB::table('service_requests')->orderBy('id')->get();
        $this->assertCount(3, $requests);
        $this->assertSame(['pending', 'pending', 'pending'], $requests->pluck('status')->all());
        $this->assertSame([0, 1, 0], $requests->pluck('active_guard_exempt')->map(fn ($value) => (int) $value)->all());
        $appointments = DB::table('guidance_appointments')->orderBy('guidance_appointment_id')->get();
        $this->assertCount(3, $appointments);
        $this->assertSame([0, 1, 0], $appointments->pluck('active_guard_exempt')->map(fn ($value) => (int) $value)->all());
    }
}
