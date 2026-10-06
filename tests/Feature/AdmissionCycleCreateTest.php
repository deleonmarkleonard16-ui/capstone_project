<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AdmissionCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Targeted test to reproduce and diagnose the 500 error when creating a cycle
 * (especially when no active cycle exists yet).
 */
class AdmissionCycleCreateTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_can_create_cycle_when_no_active_cycle_exists(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.admission.cycles.save'), [
            'cycle_name'            => 'S.Y. 2026-2027',
            'academic_year'         => '2026-2027',
            'total_items'           => 80,
            'stanine_cutoff_board'  => 4,
            'stanine_cutoff_non_board' => 3,
            'exam_weight'           => 60,
            'gwa_weight'            => 20,
            'interview_weight'      => 20,
            'set_active'            => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('admission_cycles', ['cycle_name' => 'S.Y. 2026-2027', 'status' => 'Active']);
    }

    public function test_cycle_page_loads_with_no_active_cycle(): void
    {
        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->get(route('admin.admission.index'));
        $response->assertOk();
    }

    public function test_can_create_draft_cycle_without_activating(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.admission.cycles.save'), [
            'cycle_name'            => 'Draft Cycle 2027-2028',
            'academic_year'         => '2027-2028',
            'total_items'           => 80,
            'stanine_cutoff_board'  => 4,
            'stanine_cutoff_non_board' => 3,
            'exam_weight'           => 60,
            'gwa_weight'            => 20,
            'interview_weight'      => 20,
            // set_active not set → draft
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('admission_cycles', [
            'cycle_name' => 'Draft Cycle 2027-2028',
            'is_active'  => false,
        ]);
    }

    public function test_maintenance_toggle_preserves_existing_cycle_configuration(): void
    {
        $admin = $this->adminUser();
        $cycle = AdmissionCycle::create([
            'name' => 'S.Y. 2030-2031',
            'cycle_name' => 'S.Y. 2030-2031',
            'academic_year' => '2030-2031',
            'status' => AdmissionCycle::STATUS_ACTIVE,
            'is_active' => true,
            'passing_stanine' => 5,
            'total_items' => 100,
            'exam_weight' => 50,
            'gwa_weight' => 25,
            'interview_weight' => 25,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.admission.cycles.save', $cycle), ['status' => AdmissionCycle::STATUS_MAINTENANCE])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cycle->refresh();
        $this->assertSame(AdmissionCycle::STATUS_MAINTENANCE, $cycle->status);
        $this->assertTrue((bool) $cycle->is_active);
        $this->assertSame('S.Y. 2030-2031', $cycle->cycle_name);
        $this->assertSame(100, $cycle->total_items);
        $this->assertSame('50.00', $cycle->exam_weight);
    }

    public function test_maintenance_locks_admission_tools_but_allows_reactivation(): void
    {
        $admin = $this->adminUser();
        $cycle = AdmissionCycle::create([
            'name' => 'Maintenance Cycle',
            'cycle_name' => 'Maintenance Cycle',
            'academic_year' => '2030-2031',
            'status' => AdmissionCycle::STATUS_MAINTENANCE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.admission.masterlist'))
            ->assertRedirect(route('admin.admission.index'));
        $this->actingAs($admin)
            ->get(route('admin.admission.encoding-sheet'))
            ->assertRedirect(route('admin.admission.index'));

        // Cycle management remains available, so the admin can exit maintenance.
        $this->actingAs($admin)
            ->post(route('admin.admission.cycles.activate', $cycle))
            ->assertRedirect(route('admin.admission.masterlist', ['cycle_id' => $cycle->id]));

        $this->assertDatabaseHas('admission_cycles', [
            'id' => $cycle->id,
            'status' => AdmissionCycle::STATUS_ACTIVE,
            'is_active' => true,
        ]);
    }
}
