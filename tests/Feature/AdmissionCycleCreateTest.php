<?php

namespace Tests\Feature;

use App\Models\User;
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
}
