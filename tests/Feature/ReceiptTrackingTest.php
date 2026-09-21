<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_aliases_persist_student_details_and_prefill_claim_modal(): void
    {
        Storage::fake('local');
        foreach (['payment_slip', 'proof'] as $field) {
            $entry = ServiceRequest::create(['reference' => ServiceRequest::newReference('good-moral'),
                'service' => 'good-moral', 'first_name' => 'Ana', 'last_name' => 'Cruz',
                'student_status' => 'alumni', 'student_number' => 'STUDENT-'.$field,
                'course' => 'BSIT', 'purpose' => 'Employment', 'status' => 'pending']);
            $date = now()->subDays(3)->toDateString();
            $this->postJson(route('portal.receipt'), [
                'reference' => $entry->reference, 'or_number' => 'OR-001234', 'or_date' => $date,
                $field => $this->receipt(),
            ])->assertOk();
            $entry->refresh();
            $this->assertSame('OR-001234', $entry->or_number);
            $this->assertSame($date, $entry->or_date->toDateString());
            Storage::disk('local')->assertExists($entry->proof_path);
            $entry->update(['status' => 'ready']);
            $user = new User();
            $role = new Role();
            $role->slug = 'staff';
            $user->setRelation('roleLookup', $role);
            $this->actingAs($user);
            $html = view('staff.partials.document-actions', ['entry' => $entry])->render();
            $this->assertStringContainsString('value="OR-001234"', $html);
            $this->assertStringContainsString('value="'.$date.'"', $html);
        }
    }

    public function test_guidance_receipt_aliases_update_appointments_and_fee_stubs(): void
    {
        Storage::fake('local');
        foreach (['psychological', 'personality', 'career'] as $category) {
            $this->post('/portal/requests', ['service' => 'testing', 'first_name' => 'Ana',
                'last_name' => 'Cruz', 'student_status' => 'alumni', 'student_number' => '2020-'.$category,
                'course' => 'BSIT', 'purpose' => 'Employment', 'reason' => 'OJT',
                'tests' => [$category], 'consent' => 1])->assertSessionHasNoErrors();
            $entry = ServiceRequest::latest('id')->firstOrFail();
            $this->assertSame('₱60.00', $entry->official_fee_formatted);
            $this->assertSame('₱60.00', $entry->guidanceAppointments->first()->official_fee_formatted);
            $tracked = $this->postJson('/api/track-request', ['reference' => $entry->reference])->assertOk();
            $this->assertStringContainsString('₱60.00', $tracked->json('details_html'));
            $this->postJson(route('guidance.receipt'), ['reference' => $entry->reference,
                'or_number' => 'OR-987', 'or_date' => now()->toDateString(),
                ($category === 'personality' ? 'payment_slip' : 'proof') => $this->receipt(),
            ])->assertOk();
            $appointment = $entry->guidanceAppointments()->firstOrFail();
            $this->assertSame('Receipt Uploaded', $appointment->status);
            $this->assertSame('OR-987', $appointment->or_number);
            $this->assertSame(now()->toDateString(), $appointment->or_date->toDateString());
            Storage::disk('local')->assertExists($appointment->payment_slip_path);
        }
        $entry = new ServiceRequest(['service' => 'good-moral', 'copies' => 1]);
        $this->assertSame('₱60.00', $entry->official_fee_formatted);
    }

    public function test_receipt_requires_image_and_valid_receipt_details(): void
    {
        $this->postJson(route('guidance.receipt'), ['reference' => 'G-ABCD',
            'or_date' => now()->addDay()->toDateString(),
            'proof' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['or_number', 'or_date', 'payment_slip']);
    }

    private function receipt(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII='));
    }
}
