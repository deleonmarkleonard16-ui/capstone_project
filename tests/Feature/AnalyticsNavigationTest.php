<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guidance_analytics_renders_for_both_roles(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route($role.'.analytics'))->assertOk()
                ->assertSee('Executive Institutional Analytics Dashboard')
                ->assertViewHas('totalRequests')
                ->assertViewHas('programTestingVolume')
                ->assertViewHas('specialCategories');
        }
    }

    public function test_admission_analytics_and_archive_require_an_active_cycle(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.admission.analytics'))->assertRedirect(route('admin.admission.index'));
        $this->get(route('admin.admission.archive'))->assertRedirect(route('admin.admission.index'));
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('admin.admission.analytics'))->assertForbidden();
        $this->get(route('admin.admission.archive'))->assertForbidden();
    }
}
