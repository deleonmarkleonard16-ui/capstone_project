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
}
