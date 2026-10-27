<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_navigation_and_account_update_stay_within_staff_access(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('staff.settings.index'))->assertOk()
            ->assertSee('Guidance Staff')
            ->assertSee(route('staff.analytics'))
            ->assertSee(route('staff.guidance-appointments.archive'))
            ->assertDontSee(route('admin.settings.index'));
        $this->post(route('staff.settings.update'), [
            'name' => 'Updated Staff', 'email' => $staff->email,
            'current_password' => 'password', 'role' => 'admin',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Updated Staff', $staff->fresh()->name);
        $this->assertSame('staff', $staff->fresh()->role);
        $this->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_staff_changes_require_current_password(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->post(route('staff.settings.update'), [
            'name' => 'Changed', 'email' => $staff->email, 'current_password' => 'wrong',
        ])->assertSessionHasErrors('current_password');
        $this->assertNotSame('Changed', $staff->fresh()->name);
    }

    public function test_new_staff_chooses_whether_to_keep_or_change_temporary_password(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'password_setup_required' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect(route('staff.password-choice'));

        $this->get(route('staff.password-choice'))->assertOk()->assertSee('Change Password');
        $this->post(route('staff.password-choice.keep'))->assertRedirect(route('staff.dashboard'));
        $this->assertFalse($staff->fresh()->password_setup_required);
    }
}
