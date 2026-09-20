<?php

namespace Tests\Feature;

use App\Models\GuidanceAppointment;
use App\Models\Role;
use App\Models\User;
use App\Services\GuidanceTestScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuidancePipelineTest extends TestCase
{
    use RefreshDatabase;

    private function requestAssessment(string $test = 'gad7'): GuidanceAppointment
    {
        Storage::fake('local');
        $this->post(route('guidance.request.store'), [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'student_status' => 'alumni',
            'test_type' => $test, 'payment_slip' => UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII=')), 'consent' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect('/portal?service=good-moral#track');
        return GuidanceAppointment::latest()->firstOrFail();
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->role_id = Role::where('slug', 'staff')->value('id');
        $user->save();
        return $user;
    }

    public function test_complete_verified_workflow_and_token_replay_protection(): void
    {
        $this->get(route('guidance.request'))->assertRedirect('/portal?service=testing#request');
        $this->get('/portal?service=good-moral')->assertOk()->assertSee('guidance-track', false)->assertSee('guidance-tracking.js');
        $appointment = $this->requestAssessment();
        $this->assertSame('Receipt Uploaded', $appointment->status);
        Storage::disk('local')->assertExists($appointment->payment_slip_path);
        $this->postJson(route('guidance.track'), ['request_code' => $appointment->request_code])->assertOk()->assertJsonPath('url', null);
        $this->actingAs($this->staff())->post(route('staff.guidance-appointments.verify', $appointment))->assertRedirect();
        $token = $appointment->fresh()->qrCode->token;
        $this->post(route('staff.guidance-appointments.verify', $appointment))->assertRedirect();
        $this->assertDatabaseCount('guidance_test_qr_codes', 1);
        $this->get(route('staff.guidance.index'))->assertOk()->assertSee('Approved')->assertSee('MARIA');
        $this->get(route('staff.guidance-appointments.receipt', $appointment))->assertOk();
        auth()->logout();
        $this->postJson(route('guidance.track'), ['request_code' => $appointment->request_code])->assertJsonPath('url', route('guidance.take', $token));
        $this->get(route('guidance.take', $token))->assertOk()->assertSee('Start Assessment in Fullscreen Mode')->assertHeader('Referrer-Policy', 'no-referrer');
        $answers = array_fill(1, 7, 3);
        $this->postJson(route('guidance.submit', $token), ['answers' => $answers])->assertForbidden();
        $this->postJson(route('guidance.start', $token))->assertOk();
        $this->postJson(route('guidance.submit', $token), ['answers' => [1 => 9]])->assertUnprocessable();
        $this->assertSame('In-Progress', $appointment->fresh()->status);
        $this->postJson(route('guidance.submit', $token), ['answers' => $answers])->assertOk()->assertJsonPath('status', 'Completed');
        $appointment->refresh();
        $this->assertFalse($appointment->qrCode->is_active);
        $this->assertSame(21, $appointment->response->score_summary['scores']['total']);
        $this->assertSame('Severe', $appointment->response->score_summary['interpretation']['severity']);
        $this->postJson(route('guidance.submit', $token), ['answers' => $answers])->assertGone();
        $this->get(route('guidance.take', $token))->assertGone();
        $this->assertDatabaseCount('guidance_test_responses', 1);
        $this->postJson(route('guidance.track'), ['request_code' => $appointment->request_code])->assertJsonPath('url', null)->assertJsonMissingPath('score_summary');
        $this->actingAs($this->staff())->getJson(route('staff.guidance-appointments.results'))->assertOk()->assertJsonPath('0.score_summary.scores.total', 21);
        $this->postJson(route('staff.guidance-appointments.verify', $appointment))->assertConflict();
        $this->assertFalse($appointment->fresh()->qrCode->is_active);
    }

    public function test_private_endpoints_and_unconfigured_instruments_are_rejected(): void
    {
        $appointment = $this->requestAssessment();
        $this->postJson(route('staff.guidance-appointments.verify', $appointment))->assertUnauthorized();
        $this->getJson(route('staff.guidance-appointments.receipt', $appointment))->assertUnauthorized();
        $this->getJson(route('staff.guidance-appointments.results'))->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role_id' => null]));
        $this->postJson(route('staff.guidance-appointments.verify', $appointment))->assertForbidden();
        $this->getJson(route('staff.guidance-appointments.receipt', $appointment))->assertForbidden();
        $this->postJson(route('guidance.request.store'), [
            'first_name' => 'Test', 'last_name' => 'Student', 'student_status' => 'student',
            'test_type' => 'bfpi', 'consent' => 1, 'payment_slip' => UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII=')),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('guidance_appointments', 1);
        $this->post(route('guidance.test.submit', ['reference'=>'old-reference', 'test'=>'gad7']))->assertGone();
    }

    public function test_missing_receipts_cannot_be_approved(): void
    {
        $appointment = $this->requestAssessment();
        Storage::disk('local')->delete($appointment->payment_slip_path);
        $this->actingAs($this->staff())->postJson(route('staff.guidance-appointments.verify', $appointment))->assertUnprocessable();
        $this->assertDatabaseCount('guidance_test_qr_codes', 0);
        $this->assertSame('Receipt Uploaded', $appointment->fresh()->status);
    }

    public function test_scoring_boundaries_and_bfpi_subscales(): void
    {
        $service = app(GuidanceTestScoringService::class);
        $maps = ['depression'=>[3,5,10,13,16,17,21], 'anxiety'=>[2,4,7,9,15,19,20], 'stress'=>[1,6,8,11,12,14,18]];
        $bounds = ['depression'=>[4,6,10,13], 'anxiety'=>[3,5,7,9], 'stress'=>[7,9,12,16]];
        $labels = ['Normal','Mild','Moderate','Severe','Extremely Severe'];
        foreach ($maps as $scale => $items) {
            foreach ($bounds[$scale] as $index => $bound) {
                foreach ([$bound, $bound+1] as $total) {
                    $answers = array_fill(1, 21, 0); $remaining = $total;
                    foreach ($items as $item) { $answers[$item] = min(3, $remaining); $remaining -= $answers[$item]; }
                    $score = $service->score('dass21', $answers);
                    $this->assertSame($total, $score['scores'][$scale]);
                    $this->assertSame($labels[$index + ($total > $bound ? 1 : 0)], $score['interpretation'][$scale]);
                }
            }
        }
        foreach ([0=>'Minimal',4=>'Minimal',5=>'Mild',9=>'Mild',10=>'Moderate',14=>'Moderate',15=>'Severe',21=>'Severe'] as $total=>$label) {
            $answers = array_fill(1, 7, 0); $remaining = $total;
            foreach ($answers as $item=>$_) { $answers[$item] = min(3, $remaining); $remaining -= $answers[$item]; }
            $this->assertSame($label, $service->score('gad7', $answers)['interpretation']['severity']);
        }
        $this->assertSame(27, $service->score('phq9', array_fill(1, 9, 3))['scores']['total']);
        config(['guidance.bfpi'=>['items'=>4,'subscales'=>['a'=>[1,2],'b'=>[3,4]],'reverse_items'=>[4],'overlap'=>'Average']]);
        $result = $service->score('bfpi', [1=>3.14,2=>3.14,3=>5,4=>1]);
        $this->assertSame('Average', $result['interpretation']['a']);
        $this->assertEquals(5, $result['scores']['b']);
        $this->assertSame('Very High', $result['interpretation']['b']);
    }

    public function test_extra_answer_keys_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(GuidanceTestScoringService::class)->score('gad7', array_fill(1, 8, 0));
    }
}
