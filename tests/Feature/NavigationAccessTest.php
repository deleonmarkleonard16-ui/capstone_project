<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationAccessTest extends TestCase
{
    use RefreshDatabase;
    private function loginAsRole(string $slug): void
    {
        $user = new User();
        $user->id = 1;
        $user->name = 'Navigation Tester';
        $role = new Role();
        $role->slug = $slug;
        $user->setRelation('roleLookup', $role);
        $this->actingAs($user);
    }

    public function test_staff_dashboard_excludes_admission_and_has_guidance_navigation(): void
    {
        $this->loginAsRole('staff');
        $this->get('/staff/dashboard')->assertOk()
            ->assertSee('Staff Dashboard')->assertSee('Logout')
            ->assertSee('href="'.route('staff.dashboard').'"', false)
            ->assertSee('Psychological Assessment')->assertSee('Personality Test')->assertSee('Career Test')
            ->assertDontSee('<summary>Admission</summary>', false)->assertDontSee('Create Test Session')
            ->assertDontSee('Recent Sessions');
        $this->get('/staff/guidance-testing')->assertOk()
            ->assertSee('Guidance Request Queue')
            ->assertSee('No requests match these filters.');
    }

    public function test_staff_cannot_read_or_write_admission(): void
    {
        $this->loginAsRole('staff');
        foreach (['/admin/admission-cycle', '/admin/applicants', '/admin/applicants/create', '/admin/sessions', '/admin/sessions/create'] as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach (['/admin/applicants', '/admin/applicants/import', '/admin/sessions'] as $url) {
            $this->post($url, [])->assertForbidden();
        }
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->getName() ?? '', 'admin.sessions.') || str_starts_with($route->getName() ?? '', 'admin.applicants.')) {
                $this->assertContains('role:admin', $route->gatherMiddleware());
            }
        }
    }

    public function test_admin_keeps_admission_pages_with_three_module_sidebar_links(): void
    {
        $this->loginAsRole('admin');
        $this->get('/admin/admission-cycle')->assertOk()
            ->assertSee('Admission Cycle')->assertSee('Manage Applicants')->assertSee('Manage Test Sessions')->assertSee('Logout')
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertSee('href="'.route('admin.psychological.index').'"', false)
            ->assertSee('href="'.route('admin.personality.index').'"', false)
            ->assertSee('href="'.route('admin.career.index').'"', false)
            ->assertSee('<summary>Request Testing</summary>', false)
            ->assertSee('href="'.route('admin.good-moral').'"', false)
            ->assertSee('href="'.route('admin.exit-form').'"', false)
            ->assertDontSee('<summary>Admission</summary>', false);
    }

    public function test_guest_is_redirected_from_admission(): void
    {
        $this->get('/admin/admission-cycle')->assertRedirect('/login');
    }
}
