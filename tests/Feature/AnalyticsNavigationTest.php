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
            $this->get(route($role.'.analytics'))->assertOk()->assertSee('Guidance Analytics')
                ->assertViewHas('guidanceOnly', true)->assertViewHas('specialCategories', []);
        }
    }

    public function test_admission_destinations_render_and_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.admission.analytics'))->assertOk()->assertSee('No admission cycles available.');
        $this->get(route('admin.admission.archive'))->assertOk()->assertSee('No archived admission cycles.');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('admin.admission.analytics'))->assertForbidden();
        $this->get(route('admin.admission.archive'))->assertForbidden();
    }
}
