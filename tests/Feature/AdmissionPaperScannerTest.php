<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionBatch;
use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionPaperScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_paper_qr_returns_name_and_linked_session_and_batch(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]));
        $cycle = AdmissionCycle::create(['name' => 'Scanner', 'academic_year' => '2026-2027', 'is_active' => true]);
        $batch = AdmissionBatch::create(['admission_cycle_id' => $cycle->id, 'batch_name' => 'Batch 2', 'batch_date' => '2026-11-02']);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id, 'admission_batch_id' => $batch->id,
            'session_name' => 'Session 1', 'start_time' => now(), 'start_number' => 1, 'end_number' => 10,
            'status' => 'Scheduled',
        ]);
        $applicant = AdmissionApplicant::create([
            'admission_cycle_id' => $cycle->id, 'admission_session_id' => $session->id,
            'application_number' => 'CAT-26-0001', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'session_label' => 'Old session', 'batch_group' => 'Old batch',
        ]);
        $this->get(route('admin.admission.scan-paper'))->assertOk()
            ->assertSee('QR Verified')->assertSee('applicant-session')->assertSee('applicant-batch')
            ->assertDontSee('Verify Examinee')->assertDontSee('physical permit')->assertDontSee('photo ID');
        $lookup = route('admin.admission.scan-paper.lookup');
        $this->postJson($lookup, ['code' => $applicant->application_number])->assertOk()->assertJson([
            'name' => 'Dela Cruz, Juan', 'session' => 'Session 1', 'batch' => 'Batch 2',
            'url' => route('admin.admission.encode', $applicant),
        ]);
        $applicant->update(['admission_session_id' => null]);
        $this->postJson($lookup, ['code' => $applicant->application_number])->assertOk()
            ->assertJsonPath('session', 'Old session')->assertJsonPath('batch', 'Old batch');
        $applicant->update(['session_label' => null, 'batch_group' => null]);
        $this->postJson($lookup, ['code' => $applicant->application_number])->assertOk()
            ->assertJsonPath('session', null)->assertJsonPath('batch', null);
        $this->postJson($lookup, ['code' => 'unknown'])->assertNotFound();
        $otherCycle = AdmissionCycle::create(['name' => 'Other', 'academic_year' => '2025-2026', 'is_active' => false]);
        AdmissionApplicant::create([
            'admission_cycle_id' => $otherCycle->id, 'application_number' => 'OTHER-CYCLE',
            'first_name' => 'Other', 'last_name' => 'Applicant',
        ]);
        $this->postJson($lookup, ['code' => 'OTHER-CYCLE'])->assertNotFound();
        $applicant->forceFill(['submitted_at' => now()])->save();
        $this->postJson($lookup, ['code' => $applicant->application_number])->assertConflict();
    }
}
