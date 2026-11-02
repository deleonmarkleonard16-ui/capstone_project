<?php

namespace Tests\Feature;

use App\Models\GuidanceTestBatch;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\GuidanceCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedArchiveFilterTest extends TestCase
{
    use RefreshDatabase;

    private function batch(string $type, array $attributes = []): GuidanceTestBatch
    {
        return GuidanceTestBatch::create(array_replace([
            'batch_name' => 'ARCHIVED '.$type,
            'batch_token' => Str::random(64),
            'test_type' => $type,
            'module_type' => $type,
            'course' => 'BSIT',
            'reason_for_request' => 'Practicum',
            'status' => 'Completed',
            'archived_at' => now(),
            'created_at' => '2026-10-15 09:00:00',
        ], $attributes));
    }

    private function serviceRequest(string $service, ?int $batchId = null): ServiceRequest
    {
        return ServiceRequest::create([
            'reference' => (string) Str::uuid(),
            'service' => $service,
            'batch_id' => $batchId,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'student_status' => 'student',
            'student_number' => '2026-001',
            'email' => 'maria@example.com',
            'contact_number' => '09123456789',
            'course' => 'BSIT',
            'purpose' => 'Practicum',
            'tests' => $service === 'testing' ? ['psychological'] : null,
            'status' => 'completed',
            'archived_at' => now(),
        ]);
    }

    public function test_both_roles_can_filter_archived_batches_by_test_type_and_open_them(): void
    {
        $individual = $this->serviceRequest('testing');
        $batches = [];
        foreach (GuidanceCategories::LABELS as $key => $label) {
            $batches[$key] = $this->batch($label);
        }
        $this->batch('Psychological Assessment', [
            'batch_name' => 'ACTIVE BATCH', 'status' => 'Pending Registration', 'archived_at' => null,
        ]);

        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ($batches as $key => $batch) {
                $this->get(route($role.'.archive', ['request_type' => 'batch', 'test_type' => $key]))
                    ->assertOk()
                    ->assertViewHas('testingRequests', fn ($rows) => $rows->isEmpty())
                    ->assertViewHas('testingBatches', fn ($rows) => $rows->modelKeys() === [$batch->getKey()])
                    ->assertSee(mb_strtoupper($batch->batch_name))
                    ->assertDontSee($individual->reference)
                    ->assertDontSee('ACTIVE BATCH')
                    ->assertSee('value="batch" selected', false);

                $this->get(route($role.'.guidance-batches.index', [
                    'archived' => 1, 'category' => $key, 'q' => $batch->batch_name,
                ]))->assertOk()->assertSee($batch->batch_name);
            }
            $this->get(route($role.'.archive', ['request_type' => 'individual']))
                ->assertOk()
                ->assertViewHas('testingBatches', fn ($rows) => $rows->isEmpty())
                ->assertSee($individual->reference);
            $this->get(route($role.'.archive'))
                ->assertOk()
                ->assertViewHas('testingBatches', fn ($rows) => $rows->count() === 3)
                ->assertSee($individual->reference);
        }
    }

    public function test_individual_archive_actions_show_details_with_the_correct_module_for_both_roles(): void
    {
        $requests = [];
        foreach (['psychological', 'personality', 'career', 'good-moral', 'exit-form'] as $module) {
            $entry = $this->serviceRequest(in_array($module, ['good-moral', 'exit-form'], true) ? $module : 'testing');
            if ($entry->service === 'testing') $entry->update(['tests' => [$module]]);
            $requests[$module] = $entry;
        }
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $archive = $this->get(route($role.'.archive'))->assertOk();
            foreach ($requests as $module => $entry) {
                $url = route($role.'.requests.details', ['serviceRequest' => $entry, 'module_type' => $module]);
                $archive->assertSee('href="'.$url.'">View Details</a>', false);
                $this->get($url)->assertOk()->assertViewIs('staff.request-details')
                    ->assertSee('Request Details')->assertSee($entry->reference)->assertSee('Back to Archives');
                $this->get(route($role.'.requests.details', $entry))->assertOk()->assertViewHas('moduleType', $module);
                $this->get($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
                    ->assertViewIs('staff.partials.notification-review')->assertSee($entry->reference);
                $wrongModule = $module === 'good-moral' ? 'exit-form' : 'good-moral';
                $this->get(route($role.'.requests.details', ['serviceRequest' => $entry, 'module_type' => $wrongModule]))->assertNotFound();
            }
        }
    }

    public function test_batch_request_actions_keep_open_and_lead_to_archived_rosters(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['testing', 'good-moral', 'exit-form'] as $service) {
                $label = match ($service) { 'testing' => 'Psychological Assessment', 'good-moral' => 'Good Moral', default => 'Exit Form' };
                $batch = $this->batch($label);
                $entry = $this->serviceRequest($service, $batch->getKey());
                $parameters = ['archived' => 1, 'q' => $batch->batch_name];
                if ($service === 'testing') $parameters['category'] = 'psychological';
                $url = route($role.($service === 'testing' ? '.guidance-batches.index' : '.'.$service.'.batches'), $parameters);
                $this->get(route($role.'.archive'))->assertOk()->assertSee('href="'.e($url).'">Open</a>', false);
                $this->get($url)->assertOk()->assertSee($batch->batch_name);
                $this->get(route($role.'.requests.details', ['serviceRequest' => $entry, 'module_type' => $service === 'testing' ? 'psychological' : $service]))->assertNotFound();
            }
        }
    }

    public function test_multiple_assessments_have_details_tabs_and_invalid_modules_stay_rejected(): void
    {
        $entry = $this->serviceRequest('testing');
        $entry->update(['tests' => ['psychological', 'career']]);
        $this->get(route('admin.requests.details', $entry))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('admin.requests.details', $entry))->assertForbidden();
        $this->get(route('staff.archive', ['test_type' => 'career']))->assertOk()
            ->assertSee(route('staff.requests.details', ['serviceRequest' => $entry, 'module_type' => 'career']), false);
        foreach ($entry->tests as $module) {
            $this->get(route('staff.requests.details', ['serviceRequest' => $entry, 'module_type' => $module]))
                ->assertOk()->assertViewHas('moduleType', $module)
                ->assertSee('Requested assessments')->assertSee('Psychological Assessment')->assertSee('Career Test');
        }
        $this->getJson(route('staff.requests.details', ['serviceRequest' => $entry, 'module_type' => 'admission']))
            ->assertUnprocessable()->assertJsonValidationErrors('module_type');
    }

    public function test_legacy_testing_details_infer_the_module_from_the_linked_assessment(): void
    {
        $entry = $this->serviceRequest('testing');
        $entry->update(['tests' => null]);
        $student = \App\Models\Applicant::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'application_number' => (string) Str::uuid()]);
        $appointment = \App\Models\GuidanceAppointment::create([
            'service_request_id' => $entry->getKey(), 'applicant_id' => $student->getKey(),
            'request_code' => $entry->reference, 'test_type' => 'bfpi', 'test_category' => null,
            'status' => 'Completed', 'is_archived' => true,
        ]);
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $url = route($role.'.requests.details', $entry);
            $this->get($url)->assertOk()->assertViewHas('moduleType', 'personality')
                ->assertViewHas('appointment', fn ($record) => $record->is($appointment))
                ->assertSee('Open assessment details')->assertDontSee('data-bs-dismiss="modal" >', false);
            $this->get($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
                ->assertSee('data-bs-dismiss="modal"', false);
        }
    }

    public function test_batch_archive_honors_date_and_search_filters_and_shows_empty_state(): void
    {
        $batch = $this->batch('Psychological Assessment');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $url = route('staff.archive');
        $this->get($url.'?request_type=batch&year=2026&month=10&search=ARCHIVED')
            ->assertOk()->assertSee(mb_strtoupper($batch->batch_name));
        foreach (['year=2025', 'month=9', 'search=NO-MATCH'] as $filter) {
            $this->get($url.'?request_type=batch&'.$filter)
                ->assertOk()
                ->assertViewHas('testingBatches', fn ($rows) => $rows->isEmpty())
                ->assertSee('No archived testing requests match the selected filters.');
        }
    }

    public function test_request_type_filters_document_requests_and_legacy_testing_requests(): void
    {
        $batch = $this->batch('Psychological Assessment');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        foreach (['testing', 'good-moral', 'exit-form'] as $service) {
            $this->serviceRequest($service);
            $this->serviceRequest($service, $batch->getKey());
        }
        foreach (['individual', 'batch'] as $type) {
            $response = $this->get(route('staff.archive', ['request_type' => $type]))->assertOk();
            foreach (['testingRequests', 'goodMoralRequests', 'exitFormRequests'] as $collection) {
                $response->assertViewHas($collection, fn ($rows) => $rows->count() === 1
                    && ($type === 'batch' ? $rows->first()->batch_id === $batch->getKey() : $rows->first()->batch_id === null));
            }
        }
    }
}
