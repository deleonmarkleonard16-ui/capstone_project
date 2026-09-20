<?php

namespace Tests\Feature;

use App\Models\GuidanceRequestNotification;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_requests_increment_unread_and_can_be_reviewed_and_read(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $this->actingAs($admin)->getJson(route('admin.notifications.index'))->assertOk()->assertJsonPath('unread', 0);
        $entry = ServiceRequest::create([
            'service' => 'good-moral', 'first_name' => 'Ana', 'last_name' => 'Cruz',
            'student_status' => 'student', 'student_number' => '2026-001',
            'course' => 'BSIT', 'purpose' => 'Employment', 'reference' => 'GM-TEST-001', 'status' => 'pending',
        ]);
        $notification = GuidanceRequestNotification::firstOrFail();
        $this->actingAs($admin)->getJson(route('admin.notifications.index', ['after' => 0]))
            ->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('new.0.student', 'Ana Cruz')
            ->assertJsonPath('new.0.reference', $entry->reference)
            ->assertJsonPath('new.0.request_id', $entry->id)
            ->assertJsonPath('new.0.student_name', 'Ana Cruz')
            ->assertJsonPath('new.0.module_type', 'good-moral');
        $this->get(route('admin.notifications.review', $notification))->assertOk()->assertSee('Ana Cruz');
        $this->get(route('admin.requests.details', $entry).'?module_type=good-moral')->assertOk()->assertSee('Ana Cruz');
        $this->get(route('admin.requests.details', $entry).'?module_type=exit-form')->assertNotFound();
        $this->postJson(route('api.notifications.read', $notification))->assertOk()->assertJsonPath('unread', 0);
        $this->getJson(route('admin.notifications.index'))->assertJsonPath('unread', 0);
    }

    public function test_multi_module_testing_request_creates_one_notification_per_module(): void
    {
        ServiceRequest::create([
            'service' => 'testing', 'first_name' => 'Ana', 'last_name' => 'Cruz',
            'student_status' => 'student', 'student_number' => '2026-001',
            'course' => 'BSIT', 'purpose' => 'Admission', 'reference' => 'GT-TEST-001', 'status' => 'pending',
            'tests' => ['psychological', 'career'],
        ]);
        $this->assertEqualsCanonicalizing(['psychological', 'career'], GuidanceRequestNotification::pluck('module')->all());
    }

    public function test_clearing_one_module_keeps_other_modules_unread(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);
        foreach (['good-moral', 'exit-form'] as $module) {
            ServiceRequest::create([
                'service' => $module, 'first_name' => 'Ana', 'last_name' => 'Cruz',
                'student_status' => 'student', 'student_number' => '2026-001',
                'course' => 'BSIT', 'purpose' => 'Employment', 'reference' => strtoupper($module).'-TEST', 'status' => 'pending',
            ]);
        }
        $this->actingAs($staff)->getJson(route('staff.notifications.index'))->assertJsonPath('unread', 2);
        $this->get(route('staff.good-moral'))->assertOk()->assertSee('data-current-module="good-moral"', false)
            ->assertSee('data-module-navigation="exit-form"', false);
        $this->postJson(route('api.notifications.clear-module', ['module' => 'good-moral']))->assertOk()->assertJsonPath('unread', 1);
        $this->assertDatabaseCount('guidance_notification_reads', 1);
        $this->postJson(route('api.notifications.clear-module', ['module' => 'good-moral']))->assertOk()->assertJsonPath('unread', 1);
        $this->postJson(route('api.notifications.clear-module', ['module' => 'exit-form']))->assertOk()->assertJsonPath('unread', 0);
    }
}
