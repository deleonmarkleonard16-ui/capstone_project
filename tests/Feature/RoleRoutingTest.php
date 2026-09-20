<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_to_the_correct_role_prefix(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create([
                'role_id' => Role::where('slug', $role)->value('id'),
                'password' => 'valid-password-123',
            ]);
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'valid-password-123'])
                ->assertRedirect(route($role.'.psychological.index'));
            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }

    public function test_admin_and_staff_module_routes_are_isolated(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);
        $this->actingAs($admin)->get('/admin/good-moral')->assertOk()
            ->assertSee('href="'.route('admin.exit-form').'"', false);
        $this->get('/admin/system-settings')->assertOk();
        $this->get('/staff/good-moral')->assertForbidden();
        $this->actingAs($staff)->get('/staff/good-moral')->assertOk()
            ->assertSee('href="'.route('staff.exit-form').'"', false);
        $this->get('/admin/good-moral')->assertForbidden();
        $this->get('/admin/system-settings')->assertForbidden();
    }
}
