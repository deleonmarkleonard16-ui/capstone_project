<?php

namespace Tests\Feature;

use App\Events\AdmissionSubmissionRecorded;
use App\Models\{AdmissionApplicant, AdmissionCycle, Applicant, GuidanceAppointment, GuidanceTestResponse, Role, ServiceRequest, User};
use App\Support\{CourseCatalog, RequestFees};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Storage};
use Tests\TestCase;

class CompletionRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role = 'admin'): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]));
    }

    private function cycle(): AdmissionCycle
    {
        return AdmissionCycle::create(['name' => '2026 intake', 'academic_year' => '2026-2027', 'is_active' => true]);
    }

    public function test_application_number_import_needs_no_student_id_and_does_not_accept_scores(): void
    {
        $this->login(); $this->cycle();
        $csv = "application_number,last_name,first_name,middle_name,course,sex,4ps_osy_ip_pwd_sp,cmfl,gwa,exam_score\nAPP-26,Cruz,Ana,,Bachelor of Science in Information Technology,F,,,,80\n";
        // Supply the required numeric GWA, preserving an untrusted score column.
        $csv = str_replace('F,,,,80', 'F,,,90,80', $csv);
        $this->post(route('admin.admission.applicants.import'), ['file' => UploadedFile::fake()->createWithContent('applicants.csv', $csv)])
            ->assertSessionHasNoErrors();
        $applicant = AdmissionApplicant::where('application_number', 'APP-26')->firstOrFail();
        $this->assertNull($applicant->student_id);
        $this->assertNull($applicant->exam_score);
        $this->assertSame('BSIT', $applicant->course_choice);
        $this->get(route('admin.admission.paper', $applicant))->assertOk()->assertSee(CourseCatalog::OPTIONS['BSIT'])->assertSee('valid ID');
        $this->postJson(route('admin.admission.scan-paper.lookup'), ['code'=>'APP-26'])->assertOk()->assertJsonPath('application_number','APP-26');
        $this->login('staff');
        $this->get(route('admin.admission.scan-paper'))->assertForbidden();
        $this->postJson(route('admin.admission.scan-paper.lookup'), ['code'=>'APP-26'])->assertForbidden();
    }

    public function test_paper_answers_require_exact_item_keys_and_cannot_be_submitted_twice(): void
    {
        Event::fake([AdmissionSubmissionRecorded::class]);
        $this->login(); $cycle = $this->cycle();
        $applicant = AdmissionApplicant::create(['admission_cycle_id'=>$cycle->id,'application_number'=>'APP-1','first_name'=>'Ana','last_name'=>'Cruz','course_choice'=>'BSIT']);
        $answers = array_fill_keys(range(1,80),'A');
        $this->post(route('admin.admission.answer-key.save'), ['answers'=>$answers])->assertSessionHasNoErrors();
        $bad = $answers; unset($bad[1]); $bad[81] = 'A';
        $this->postJson(route('admin.admission.encode.submit',$applicant), ['answers'=>$bad])->assertUnprocessable();
        $this->post(route('admin.admission.encode.submit',$applicant), ['answers'=>$answers])->assertRedirect();
        $this->assertEquals(80,$applicant->fresh()->exam_score);
        Event::assertDispatched(AdmissionSubmissionRecorded::class);
        $this->postJson(route('admin.admission.encode.submit',$applicant), ['answers'=>$answers])->assertConflict();
        $this->postJson(route('admin.admission.scan-paper.lookup'), ['code'=>'APP-1'])->assertConflict();
    }

    public function test_claim_requires_or_details_and_real_receipt(): void
    {
        Storage::fake('local'); $this->login('staff');
        $entry = ServiceRequest::create(['reference'=>'GM-TEST1234','service'=>'good-moral','first_name'=>'Ana','last_name'=>'Cruz','student_status'=>'student','status'=>'proof_review','proof_path'=>'missing.png']);
        $url = route('staff.good-moral.update',$entry);
        $this->patchJson($url,['action'=>'verify'])->assertConflict();
        Storage::disk('local')->assertMissing('missing.png');
        Storage::disk('local')->put('missing.png','receipt image');
        $this->patchJson($url,['action'=>'verify'])->assertOk();
        $this->patchJson($url,['action'=>'claim'])->assertUnprocessable();
        $this->patchJson($url,['action'=>'claim','or_number'=>'12345','or_date'=>now()->toDateString()])->assertOk();
        $this->assertSame('12345',$entry->fresh()->or_number);
        $this->assertNotNull($entry->fresh()->archived_at);
        $this->assertDatabaseCount('guidance_appointments',0);
        $this->assertSame(60,RequestFees::total('good-moral'));
        $this->assertSame(180,RequestFees::total('testing',['career','personality','psychological']));
        $this->assertNull(RequestFees::total('exit-form'));
    }

    public function test_module_tabs_are_analytics_first(): void
    {
        $this->login('staff');
        foreach (['good-moral','exit-form','personality','career','psychological'] as $module) {
            $route = 'staff.'.$module.(in_array($module,['good-moral','exit-form']) ? '' : '.index');
            $this->get(route($route))->assertOk()->assertSeeInOrder(['Analytics','Individual Request Queue','Bundled / Batch Queue','Archives']);
        }
    }

    public function test_career_report_is_private_scored_and_downloadable(): void
    {
        $applicant = Applicant::create(['application_number'=>'23-SC-1234','first_name'=>'Ana','last_name'=>'Cruz']);
        $appointment = GuidanceAppointment::create(['applicant_id'=>$applicant->id,'request_code'=>'G-T123','test_type'=>'career','test_category'=>'career','status'=>'Completed']);
        GuidanceTestResponse::create(['guidance_appointment_id'=>$appointment->getKey(),'applicant_id'=>$applicant->id,'test_type'=>'career','answers'=>[], 'score_summary'=>[
            'test_type'=>'career','scores'=>['Realistic'=>5,'Social'=>4,'Artistic'=>3,'Investigative'=>2,'Enterprising'=>1,'Conventional'=>1],
            'interpretation'=>['Realistic'=>'Extremely interested','Social'=>'Very interested','Artistic'=>'Moderately interested'],
        ]]);
        $url = route('staff.guidance-appointments.career-report',$appointment);
        $this->getJson($url)->assertUnauthorized(); $this->login('staff');
        $this->get($url)->assertOk()->assertSee('Realistic, Social, Artistic')->assertSee('Ms. Noemi C. Carlos')->assertHeader('Cache-Control','no-store, private');
        $this->get($url.'?format=pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        $appointment->update(['status'=>'Approved']);
        $this->get($url)->assertNotFound();
    }
}
