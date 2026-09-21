<?php

namespace Tests\Feature;

use App\Models\GuidanceTestBatch;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): void
    {
        $user = new User();
        $user->id = 101;
        $user->name = 'Guidance Staff';
        $role = new Role();
        $role->slug = 'staff';
        $user->setRelation('roleLookup', $role);
        $this->actingAs($user);
    }

    public function test_document_batches_verify_receipts_and_archive_without_exam_appointments(): void
    {
        $this->staff();
        foreach (['good-moral' => 'Good Moral', 'exit-form' => 'Exit Form'] as $module => $label) {
            $file = UploadedFile::fake()->createWithContent('roster.csv', "student_id,fname,mname,lname\n26-SC-1111,ANA,,CRUZ\n");
            $this->post(route('staff.'.$module.'.batches.store'), [
                'batch_name' => strtoupper($module).'-BATCH', 'course' => 'BSIT',
                'reason_for_request' => 'Graduation', 'roster' => $file,
                'module_type' => 'Psychological Assessment',
            ])->assertSessionHasNoErrors();

            $batch = GuidanceTestBatch::where('batch_name', strtoupper($module).'-BATCH')->firstOrFail();
            $this->assertSame($label, $batch->module_type);
            $this->assertNull($batch->test_type);
            $entry = ServiceRequest::where('batch_id', $batch->getKey())->firstOrFail();
            $this->assertSame($module, $entry->service);
            $this->get(route('staff.'.$module))->assertOk()
                ->assertSee('Individual Request Queue')->assertSee('Bundled / Batch Queue')
                ->assertSee('Archives')->assertSee('Analytics')
                ->assertSee('+ Create Bundled Request')->assertDontSee(strtoupper($module).'-BATCH');
            $this->get(route('staff.'.$module.'.batches'))->assertOk()->assertSee(strtoupper($module).'-BATCH')->assertSee('Batch join QR code');
            $this->get(route('staff.'.$module.'.analytics'))->assertOk()->assertSee('Completion alert:');
            $this->patch(route('staff.'.$module.'.update', $entry), ['action' => 'verify'])->assertStatus(409);
            $this->get(route('document.batch.join', $batch->batch_token))->assertOk()->assertSee('Verify roster identity');
            $this->post(route('document.batch.verify', $batch->batch_token), [
                'student_id' => '26-SC-1111', 'first_name' => 'ANA', 'middle_name' => '', 'last_name' => 'WRONG',
            ])->assertSessionHasErrors('student_id');
            $this->post(route('document.batch.verify', $batch->batch_token), [
                'student_id' => '26-SC-1111', 'first_name' => 'ANA', 'middle_name' => '', 'last_name' => 'CRUZ',
            ])->assertSessionHasNoErrors();
            $this->post(route('document.batch.receipt', $batch->batch_token), [
                'receipt' => UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII=')),
            ])->assertSessionHasNoErrors();
            $this->assertSame('proof_review', $entry->fresh()->status);
            $this->get(route('staff.documents.proof', $entry))->assertOk();
            $this->patch(route('staff.'.$module.'.update', $entry), ['action' => 'verify'])->assertSessionHasNoErrors();
            $this->assertSame('ready', $entry->fresh()->status);
            $this->get(route('staff.'.$module.'.batches'))->assertSee('Approved / Ready for Pickup')->assertSee('Mark as Claimed / Completed');
            $this->patch(route('staff.'.$module.'.update', $entry), ['action' => 'claim', 'or_number' => 'OR-123', 'or_date' => now()->toDateString()])->assertSessionHasNoErrors();
            $this->assertSame('completed', $entry->fresh()->status);
            $this->assertNotNull($batch->fresh()->archived_at);
            $this->get(route('staff.'.$module.'.archive'))->assertOk()->assertSee(strtoupper($module).'-BATCH');
        }
        $this->get(route('staff.good-moral.batches'))->assertDontSee('EXIT-FORM-BATCH');
        $this->get(route('staff.exit-form.batches'))->assertDontSee('GOOD-MORAL-BATCH');
        $this->get(route('staff.good-moral.archive'))->assertDontSee('EXIT-FORM-BATCH');
        $this->get(route('staff.exit-form.archive'))->assertDontSee('GOOD-MORAL-BATCH');
        $this->assertDatabaseCount('guidance_appointments', 0);
        $this->assertDatabaseCount('guidance_test_responses', 0);
    }
}
