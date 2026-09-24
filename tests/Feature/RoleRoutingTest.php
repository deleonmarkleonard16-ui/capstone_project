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
            $otherRole = $role === 'admin' ? 'staff' : 'admin';
            $this->withSession(['user_role' => $otherRole, 'url.intended' => '/'.$otherRole.'/analytics'])
                ->post(route('login.store'), [
                    'email' => $user->email,
                    'password' => 'valid-password-123',
                    'role' => $otherRole,
                    'role_id' => Role::where('slug', $otherRole)->value('id'),
                    'user_role' => $otherRole,
                ])
                ->assertRedirect(route($role.'.analytics'))
                ->assertSessionHas('user_role', $role);
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'))->assertRedirect(route('login'))
                ->assertSessionMissing('user_role');
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

    public function test_wrong_password_cannot_authenticate_either_role(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'password' => 'correct-password']);

            $this->withSession(['user_role' => 'admin'])->from(route('login'))
                ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email')
                ->assertSessionMissing('user_role')
                ->assertSessionMissing('_old_input.password');

            $this->assertGuest();
        }
    }

    public function test_inactive_account_cannot_authenticate(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => false, 'password' => 'correct-password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('user_role');

        $this->assertGuest();
    }

    public function test_unsupported_database_role_cannot_authenticate(): void
    {
        $role = Role::create(['name' => 'Other', 'slug' => 'other']);
        $user = User::factory()->create(['role_id' => $role->id, 'password' => 'correct-password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('user_role');

        $this->assertGuest();
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

    public function test_login_page_renders_password_toggle(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="togglePassword"', false)
            ->assertSee('togglePasswordVisibility', false);
    }

    public function test_navigation_renders_dynamic_role_pill_badge(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);

        $adminRes = $this->actingAs($admin)->withSession(['user_role' => 'staff'])->get(route('admin.analytics'));
        $adminRes->assertOk();
        $adminRes->assertSee('role-pill admin', false);
        $adminRes->assertSee('ADMIN', false);
        $this->assertSame(2, substr_count($adminRes->getContent(), 'badge role-pill admin'));
        $adminRes->assertDontSee('badge role-pill staff', false);

        $staffRes = $this->actingAs($staff)->withSession(['user_role' => 'admin'])->get(route('staff.analytics'));
        $staffRes->assertOk();
        $staffRes->assertSee('role-pill staff', false);
        $staffRes->assertSee('STAFF', false);
        $this->assertSame(2, substr_count($staffRes->getContent(), 'badge role-pill staff'));
        $staffRes->assertDontSee('badge role-pill admin', false);
    }
}
