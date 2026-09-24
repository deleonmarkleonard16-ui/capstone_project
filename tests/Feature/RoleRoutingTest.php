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
                ->assertRedirect(route($role.'.analytics'));
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

    public function test_seeder_creates_login_ready_users(): void
    {
        $this->seed(\Database\Seeders\AdmissionTestSeeder::class);

        $this->post(route('login.store'), [
            'email' => 'admin@psu-scc.test',
            'password' => 'password',
        ])->assertRedirect(route('admin.analytics'));

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => 'staff@psu-scc.test',
            'password' => 'password',
        ])->assertRedirect(route('staff.analytics'));
    }

    public function test_user_create_artisan_command(): void
    {
        $this->artisan('user:create', [
            'email' => 'custom-admin@psu.edu.ph',
            'password' => 'MySecurePass123',
            'role' => 'admin',
        ])->assertSuccessful();

        $this->post(route('login.store'), [
            'email' => 'custom-admin@psu.edu.ph',
            'password' => 'MySecurePass123',
        ])->assertRedirect(route('admin.analytics'));
    }
}
