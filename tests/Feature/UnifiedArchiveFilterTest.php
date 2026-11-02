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
