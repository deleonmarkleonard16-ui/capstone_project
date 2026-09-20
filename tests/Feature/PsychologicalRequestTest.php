<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PsychologicalRequestTest extends TestCase
{
    use RefreshDatabase;

    // Historical requests retain the original paid-stub workflow.
    private function submit(): ServiceRequest
    {
        return ServiceRequest::create([
            'reference'=>ServiceRequest::newReference('testing'), 'service'=>'testing',
            'first_name'=>'Ana', 'last_name'=>'Sample', 'student_status'=>'alumni',
            'student_number'=>'', 'course'=>'BSIT', 'purpose'=>'Field Study',
            'tests'=>['psychological'], 'status'=>'approved',
        ]);
    }
    private function staff(): void
    {
        $user = new User();$user->id=99;$user->name='Staff';
        $role=new Role();$role->slug='staff';$user->setRelation('roleLookup',$role);
        $this->actingAs($user);
    }

    public function test_approval_receipt_scheduling_tracking_and_archive(): void
    {
        Storage::fake('local');
        $entry=$this->submit();$this->staff();
        $url='/staff/psychological/'.$entry->id;
        $this->patch($url,['status'=>'scheduled','scheduled_at'=>now()->addDay()->format('Y-m-d\TH:i'),'venue'=>'Guidance'])->assertSessionHasErrors('status');
        $this->patch($url,['status'=>'approved'])->assertSessionHasNoErrors();
        $this->post('/portal/track',['reference'=>$entry->reference,'email'=>$entry->email])->assertOk()->assertSee('UPLOAD STUB FOR VERIFICATION');
        $this->post('/portal/psychological/receipt',['reference'=>$entry->reference,'email'=>$entry->email,'proof'=>UploadedFile::fake()->create('receipt.pdf',20,'application/pdf')])->assertSessionHasNoErrors()->assertRedirect('/portal');
        $this->assertSame('proof_review',$entry->fresh()->status);
        $this->get($url.'/receipt')->assertOk();
        $schedule=now()->addDay()->timezone('Asia/Manila')->format('Y-m-d\TH:i');
        $this->patch($url,['status'=>'scheduled','scheduled_at'=>$schedule,'venue'=>'Guidance Office'])->assertSessionHasNoErrors();
        $this->assertSame($schedule,$entry->fresh()->scheduled_at->timezone('Asia/Manila')->format('Y-m-d\TH:i'));
        $this->post('/portal/track',['reference'=>$entry->reference,'email'=>$entry->email])->assertOk()->assertSee('Guidance Office')->assertSee('Philippine time');
        $this->patch($url,['status'=>'completed'])->assertSessionHasErrors('status');
        $this->travel(2)->days();
        $this->patch($url,['status'=>'completed'])->assertSessionHasNoErrors();
        $this->assertNotNull($entry->fresh()->archived_at);
        $this->get('/staff/psychological')->assertOk()->assertDontSee('Ana Sample');
        $this->get('/staff/psychological/archive')->assertOk()->assertSee('ANA SAMPLE');
        $this->get('/staff/psychological/analytics')->assertOk()->assertSee('Psychological Assessment Analytics');
        $this->travelBack();
    }

    public function test_private_receipts_and_upload_state_are_protected(): void
    {
        Storage::fake('local');$entry=$this->submit();$entry->update(['status'=>'completed']);
        $payload=['reference'=>$entry->reference,'email'=>$entry->email,'proof'=>UploadedFile::fake()->create('receipt.pdf',20,'application/pdf')];
        $this->post('/portal/psychological/receipt',$payload)->assertSessionHasErrors('proof');
        $entry->update(['status'=>'approved']);
        $payload['reference']=(string)\Illuminate\Support\Str::uuid();
        $this->post('/portal/psychological/receipt',$payload)->assertNotFound();
        $this->get('/staff/psychological/'.$entry->id.'/receipt')->assertRedirect('/login');
        $this->get('/staff/psychological')->assertRedirect('/login');
    }

    public function test_generic_update_cannot_bypass_workflow_and_search_excludes_other_tests(): void
    {
        $entry=$this->submit();$this->staff();
        $this->patch('/staff/requests/'.$entry->id,['status'=>'completed'])->assertSessionHasErrors('status');
        $other=$entry->replicate();$other->reference=(string)\Illuminate\Support\Str::uuid();$other->tests=['career'];$other->first_name='CareerOnly';$other->save();
        $this->get('/staff/psychological?q=Ana')->assertOk()->assertSee('ANA SAMPLE')->assertDontSee('CareerOnly');
        $this->get('/staff/psychological?status=scheduled')->assertOk()->assertDontSee('Ana Sample');
        $this->patch('/staff/psychological/'.$other->id,['status'=>'approved'])->assertSessionHasNoErrors();
    }

    public function test_psychological_details_are_required_and_invalid_receipt_is_rejected(): void
    {
        $this->post('/portal/requests', ['service'=>'testing','tests'=>['psychological']])
            ->assertSessionHasErrors(['reason']);
        Storage::fake('local');$entry=$this->submit();$entry->update(['status'=>'approved']);
        $this->post('/portal/psychological/receipt', ['reference'=>$entry->reference,'email'=>$entry->email,
            'proof'=>UploadedFile::fake()->create('script.html',10,'text/html')])->assertSessionHasErrors('proof');
        $this->assertSame('approved',$entry->fresh()->status);
        $this->get('/portal/track')->assertRedirect('/portal#track');
    }
}
