<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function receipt(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII='));
    }

    private function payload(string $service = 'testing'): array
    {
        return ['service'=>$service, 'first_name'=>'Sample', 'last_name'=>'Student',
            'student_status'=>'alumni', 'student_number'=>'2020-123', 'course'=>'BSIT',
            'purpose'=>'Employment', 'reason'=>'OJT', 'tests'=>['psychological'],
            'copies'=>2, 'consent'=>1];
    }

    private function staff(): User
    {
        return User::factory()->create(['role_id'=>Role::where('slug', 'staff')->value('id')]);
    }

    private function uploadReceipt(ServiceRequest $entry): void
    {
        $this->postJson(route('guidance.receipt'), ['reference'=>$entry->reference, 'proof'=>$this->receipt()])->assertOk();
    }

    public function test_existing_form_creates_pending_appointments_with_one_shared_reference(): void
    {
        $this->get('/')->assertRedirect('/portal');
        $this->get('/portal')->assertOk()->assertDontSee('name="payment_slip"', false)->assertSee('/api/track-request')->assertDontSee('Request a guidance assessment');
        $data = $this->payload(); $data['tests'] = ['psychological','personality','career'];
        $this->post('/portal/requests', $data + ['status'=>'completed'])->assertSessionHasNoErrors()->assertRedirect('/portal?service=testing#track');
        $entry = ServiceRequest::firstOrFail();
        $this->assertSame('pending', $entry->status);
        $this->assertMatchesRegularExpression('/^G-[A-Z0-9]{4}$/', $entry->reference);
        $this->assertSame('2020-123', $entry->student_number);
        $this->assertSame('BSIT', $entry->course);
        $this->assertSame('OJT', $entry->purpose);
        $this->assertCount(3, $entry->guidanceAppointments);
        $this->assertCount(1, $entry->guidanceAppointments->pluck('applicant_id')->unique());
        foreach ($entry->guidanceAppointments as $appointment) {
            $this->assertSame('Pending Payment', $appointment->status);
            $this->assertSame($entry->proof_path, $appointment->payment_slip_path);
        }
        $this->assertNull($entry->proof_path);
        $this->get('/portal')->assertOk()->assertSee('TESTING REQUEST STUB')->assertSee($entry->reference)->assertSee('PRINT / SAVE STUB')->assertSee('data-guidance-receipt')
            ->assertSee('data-auto-track="true"', false)->assertSee('value="'.$entry->reference.'"', false);
        $tracked = $this->postJson('/api/track-request', ['reference'=>$entry->reference])->assertOk()->assertJsonCount(3, 'passes')->assertJsonPath('status', 'Pending Payment')->assertJsonPath('passes.0.direct_test_link', null)->assertJsonPath('receipt_uploaded', false);
        $this->assertStringContainsString('data-guidance-receipt', $tracked->json('details_html'));
        $this->travel(6)->days();
        ServiceRequest::expirePendingRequests();
        $this->assertFalse($entry->checkAndApplyExpiration());
        $this->assertSame('pending', $entry->fresh()->status);
        $this->uploadReceipt($entry);
        $entry->refresh();
        Storage::disk('local')->assertExists($entry->proof_path);
        $this->assertSame('proof_review', $entry->status);
        foreach ($entry->guidanceAppointments as $appointment) {
            $this->assertSame($entry->proof_path, $appointment->payment_slip_path);
            $this->assertSame('Receipt Uploaded', $appointment->status);
        }
        $tracked = $this->postJson('/api/track-request', ['reference'=>$entry->reference])->assertJsonPath('receipt_uploaded', true)->assertJsonPath('passes.0.qr_image', null);
        $this->assertStringNotContainsString('data-guidance-receipt', $tracked->json('details_html'));
    }

    public function test_psychological_qr_requires_receipt_but_no_paper_instrument_selection(): void
    {
        foreach (['student' => 'staff', 'alumni' => 'admin'] as $studentStatus => $role) {
            auth()->logout();
            $this->post('/portal/requests', array_replace($this->payload(), ['student_status' => $studentStatus]))->assertSessionHasNoErrors();
            $entry = ServiceRequest::latest('id')->firstOrFail();
            $appointment = $entry->guidanceAppointments()->firstOrFail();
            $url = route($role.'.guidance-appointments.verify', $appointment);
            $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]));
            $this->postJson($url)->assertUnprocessable();
            $this->assertNull($appointment->fresh()->qrCode);
            $this->uploadReceipt($entry);
            foreach (['psychological.index', 'guidance.index'] as $page) {
                $this->get(route($role.'.'.$page))->assertOk()->assertSee('Verify &amp; Generate QR', false)
                    ->assertDontSee('Paper instrument')->assertDontSee('Select the assigned paper test');
            }
            $this->post($url)->assertRedirect()->assertSessionHasNoErrors();
            $this->post($url)->assertRedirect()->assertSessionHasNoErrors();
            $appointment->refresh();
            $this->assertSame('Approved', $appointment->status);
            $this->assertSame('dass21', $appointment->test_type);
            $this->assertSame(['dass21', 'phq9', 'gad7'], $appointment->test_types);
            $this->assertTrue($appointment->qrCode->is_active);
            $this->assertSame(1, $appointment->qrCode()->count());
        }
    }

    public function test_existing_reference_tracks_verified_qr_image_and_completion(): void
    {
        $this->post('/portal/requests', $this->payload())->assertSessionHasNoErrors();
        $entry = ServiceRequest::firstOrFail(); $appointment = $entry->guidanceAppointments()->firstOrFail();
        $url = route('staff.guidance-appointments.verify', $appointment);
        $this->postJson($url, ['test_type'=>'gad7'])->assertUnauthorized();
        $this->actingAs($this->staff());
        $this->get('/staff/guidance-testing')->assertOk()->assertSee('receipt-preview')->assertSee('2020-123')->assertSee('BSIT')->assertSee('OJT');
        $this->postJson($url, [])->assertUnprocessable();
        $this->postJson($url, ['test_type'=>'bfpi'])->assertUnprocessable();
        // gad7 is valid for a psychological appointment (test_types includes dass21/phq9/gad7)
        // but still returns 422 because status is still 'Pending Payment' (no receipt yet)
        $this->postJson($url, ['test_type'=>'gad7'])->assertUnprocessable();
        $this->assertDatabaseCount('guidance_test_qr_codes', 0);
        $this->uploadReceipt($entry);
        $this->post($url, ['test_type'=>'gad7'])->assertSessionHasNoErrors();
        $this->post($url, ['test_type'=>'phq9'])->assertSessionHasNoErrors();
        $this->assertSame('gad7', $appointment->fresh()->test_type);
        $this->assertDatabaseCount('guidance_test_qr_codes', 1);
        auth()->logout();
        // The portal uses the module label while retaining instrument keys internally.
        $result = $this->postJson('/api/track-request', ['reference'=>' '.strtolower($entry->reference).' '])
            ->assertOk()->assertJsonPath('status', 'Approved')->assertJsonPath('passes.0.test', 'Psychological Assessment')
            ->assertJsonMissingPath('answers')->assertJsonMissingPath('score_summary');
        $image = $result->json('qr_image');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $image);
        $svg = base64_decode(substr($image, strpos($image, ',') + 1));
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('shape-rendering="crispEdges"', $svg);
        $token = $appointment->fresh()->qrCode->token;
        $this->assertSame(route('guidance.take', $token), $result->json('direct_test_link'));
        $this->get(route('guidance.take', $token))->assertOk()->assertSee('type="radio"', false);
        $this->postJson(route('guidance.start', $token))->assertOk();
        // Multi-instrument: submit nested answers for dass21(21), phq9(9), gad7(7)
        $answers = [
            'dass21' => array_fill(1, 21, 1),
            'phq9'   => array_fill(1,  9, 1),
            'gad7'   => array_fill(1,  7, 1),
        ];
        $this->submitGuidanceSections($token, $answers)->assertOk();
        $this->assertSame('completed', $entry->fresh()->status);
        $this->postJson('/api/track-request', ['reference'=>$entry->reference])->assertOk()->assertJsonPath('status', 'Completed')->assertJsonPath('qr_image', null)->assertJsonPath('direct_test_link', null);
    }

    public function test_legacy_controls_cannot_bypass_or_replace_linked_assessment_receipts(): void
    {
        $this->post('/portal/requests', $this->payload()); $entry = ServiceRequest::firstOrFail();
        $this->actingAs($this->staff());
        foreach (['approved','completed','cancelled'] as $status) {
            $this->patch('/staff/requests/'.$entry->id, ['status'=>$status])->assertSessionHasErrors('status');
        }
        $this->post('/portal/psychological/receipt', ['reference'=>$entry->reference, 'proof'=>$this->receipt()])->assertSessionHasErrors('proof');
        $this->assertSame('pending', $entry->fresh()->status);
        $this->assertDatabaseCount('guidance_test_qr_codes', 0);
        $this->uploadReceipt($entry);
        $this->postJson(route('guidance.receipt'), ['reference'=>$entry->reference, 'proof'=>$this->receipt()])->assertUnprocessable();
        $this->assertCount(1, Storage::disk('local')->allFiles('guidance-receipts'));
    }

    public function test_unconfigured_instruments_do_not_receive_a_pass(): void
    {
        config(['guidance.career.items' => 0]);
        $data = $this->payload(); $data['tests'] = ['personality','career'];
        $this->post('/portal/requests', $data)->assertSessionHasNoErrors();
        $this->uploadReceipt(ServiceRequest::firstOrFail());
        $this->actingAs($this->staff());
        foreach (ServiceRequest::firstOrFail()->guidanceAppointments as $appointment) {
            $this->postJson(route('staff.guidance-appointments.verify', $appointment))->assertUnprocessable();
        }
        $this->assertDatabaseCount('guidance_test_qr_codes', 0);
    }

    public function test_multiple_passes_complete_independently_before_parent_request_completes(): void
    {
        $data = $this->payload(); $data['tests'] = ['psychological','career'];
        $this->post('/portal/requests', $data)->assertSessionHasNoErrors();
        $entry = ServiceRequest::firstOrFail();
        $this->uploadReceipt($entry);
        $this->actingAs($this->staff());
        foreach ($entry->guidanceAppointments as $appointment) {
            $this->post(route('staff.guidance-appointments.verify', $appointment), ['test_type'=>$appointment->test_category === 'psychological' ? 'phq9' : 'career'])->assertSessionHasNoErrors();
        }
        auth()->logout();
        $this->postJson('/api/track-request', ['reference'=>$entry->reference])->assertJsonPath('status', 'Approved')->assertJsonCount(2, 'passes');
        foreach ($entry->guidanceAppointments as $index=>$appointment) {
            $appointment->refresh();
            $token = $appointment->qrCode->token;
            $this->postJson(route('guidance.start', $token))->assertOk();
            // Psychological = nested answers (dass21+phq9+gad7); Career = nested answers (career)
            if ($appointment->test_category === 'psychological') {
                $answers = [
                    'dass21' => array_fill(1, 21, 1),
                    'phq9'   => array_fill(1,  9, 1),
                    'gad7'   => array_fill(1,  7, 1),
                ];
            } else {
                $answers = ['career' => array_fill(1, 6, 3)];
            }
            $this->submitGuidanceSections($token, $answers)->assertOk();
            $this->assertSame($index === 0 ? 'processing' : 'completed', $entry->fresh()->status);
            $tracked = $this->postJson('/api/track-request', ['reference'=>$entry->reference])->assertOk();
            if ($index === 0) {
                $tracked->assertJsonPath('status', 'In-Progress')->assertJsonPath('passes.0.direct_test_link', null);
                $this->assertNotNull($tracked->json('passes.1.direct_test_link'));
            } else {
                $tracked->assertJsonPath('status', 'Completed')->assertJsonPath('passes.1.direct_test_link', null);
            }
        }
        $this->assertDatabaseCount('guidance_test_responses', 2);
    }

    public function test_receipt_upload_is_separate_and_non_images_are_rejected(): void
    {
        $this->post('/portal/requests', $this->payload())->assertSessionHasNoErrors();
        $entry = ServiceRequest::firstOrFail();
        $this->postJson(route('guidance.receipt'), ['reference'=>$entry->reference])->assertUnprocessable();
        $this->postJson(route('guidance.receipt'), ['reference'=>$entry->reference, 'proof'=>UploadedFile::fake()->create('script.html', 10, 'text/html')])->assertUnprocessable();
        $this->postJson(route('guidance.receipt'), ['reference'=>'TR-'.str_repeat('F',32), 'proof'=>$this->receipt()])->assertNotFound();
        $this->assertSame('pending', $entry->fresh()->status);
        $this->assertNull($entry->fresh()->proof_path);
        $this->assertCount(0, Storage::disk('local')->allFiles('guidance-receipts'));
    }

    public function test_student_id_and_reason_validation_is_preserved(): void
    {
        $data = $this->payload(); unset($data['student_number']); $data['student_status'] = 'student';
        $this->post('/portal/requests', $data)->assertSessionHasErrors('student_number');
        $data['student_status'] = 'alumni';
        $this->post('/portal/requests', $data)->assertSessionHasNoErrors();
        $this->assertSame('', ServiceRequest::firstOrFail()->student_number);
        $data['reason'] = 'Others';
        $this->post('/portal/requests', $data)->assertSessionHasErrors('other_reason');
        $data['other_reason'] = 'Scholarship application';
        $this->post('/portal/requests', $data)->assertSessionHasNoErrors();
        $this->assertSame('Scholarship application', ServiceRequest::latest('id')->first()->purpose);
        $data['tests'] = ['invalid'];
        $this->post('/portal/requests', $data)->assertSessionHasErrors('tests.0');
    }

    public function test_document_request_stub_upload_and_tracking_remain_available(): void
    {
        foreach (['good-moral','exit-form'] as $service) {
            $data = $this->payload($service); unset($data['payment_slip']);
            $this->post('/portal/requests', $data)->assertSessionHasNoErrors();
            $entry = ServiceRequest::latest('id')->firstOrFail();
            $this->assertSame('approved', $entry->status);
            $this->post('/portal/track', ['reference'=>$entry->reference])->assertOk()->assertSee('UPLOAD STUB FOR VERIFICATION');
            $this->postJson('/api/track-request', ['reference'=>$entry->reference])->assertOk()->assertJsonPath('status', 'Approved')->assertJsonCount(0, 'passes');
            $this->post('/portal/psychological/receipt', ['reference'=>$entry->reference, 'proof'=>UploadedFile::fake()->create('stub.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
            $this->assertSame('proof_review', $entry->fresh()->status);
            $this->actingAs($this->staff())->patch('/staff/requests/'.$entry->id, ['action'=>'verify'])->assertSessionHasNoErrors();
            $this->assertSame('ready', $entry->fresh()->status);
            $this->patch('/staff/requests/'.$entry->id, ['action'=>'claim'])->assertSessionHasNoErrors();
            $this->assertSame('completed', $entry->fresh()->status);
            $this->assertNotNull($entry->fresh()->archived_at);
        }
        $this->assertDatabaseCount('guidance_appointments', 0);
    }

    public function test_unknown_tracking_reference_reveals_no_request(): void
    {
        $this->postJson('/api/track-request', ['reference'=>'TR-'.str_repeat('A',32)])->assertNotFound();
        $this->postJson('/api/track-request', [])->assertUnprocessable();
    }

    public function test_api_payment_states_and_automatic_appointment_time(): void
    {
        $this->freezeTime();
        $created = $this->postJson('/api/submit-request', $this->payload())
            ->assertCreated()->assertJsonPath('status', 'Pending Payment');
        $reference = $created->json('request_code');
        $appointment = \App\Models\GuidanceAppointment::where('request_code', $reference)->firstOrFail();
        $this->assertNull($appointment->appointment_at);
        $this->postJson('/api/track-request', ['request_code'=>$reference])->assertJsonPath('status', 'Pending Payment');
        $this->postJson('/api/upload-receipt', ['request_code'=>$reference, 'proof'=>$this->receipt(), 'appointment_at'=>'2099-01-01'])
            ->assertOk()->assertJsonPath('status', 'Receipt Uploaded');
        $appointment->refresh();
        $this->assertSame('Receipt Uploaded', $appointment->status);
        $this->assertSame(now()->toDateTimeString(), $appointment->appointment_at->toDateTimeString());
        $uploadedAt = $appointment->appointment_at->toDateTimeString();
        $this->travel(1)->hours();
        $this->actingAs($this->staff())->post(route('staff.guidance-appointments.verify', $appointment), [
            'test_type'=>'gad7', 'appointment_at'=>'2099-01-01', 'venue'=>'Forged venue',
        ])->assertSessionHasNoErrors();
        $this->assertSame($uploadedAt, $appointment->fresh()->appointment_at->toDateTimeString());
        $this->get('/staff/guidance-testing')->assertOk()->assertSee('Show receipt')->assertSee('modal fade')
            ->assertDontSee('datetime-local')->assertDontSee('name="venue"', false);
        $this->get('/staff/psychological')->assertOk()->assertSee('Show receipt')
            ->assertDontSee('Save schedule')->assertDontSee('datetime-local');
    }
}
