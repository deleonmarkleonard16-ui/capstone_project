<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_the_two_settings_tabs(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);
        $this->actingAs($staff)->get(route('admin.settings.index'))->assertForbidden();
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()
            ->assertSee('Course / Program Management')->assertSee('Guidance Staff Account Management')
            ->assertDontSee('Security parameters')->assertDontSee('Active academic term');
    }

    public function test_disabling_staff_blocks_login_and_existing_session_without_deleting_account(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id'), 'password' => 'long-password-123']);
        $this->actingAs($admin)->post(route('admin.settings.user', $staff), [
            'name' => $staff->name, 'email' => $staff->email, 'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse($staff->fresh()->is_active);
        $this->actingAs($staff->fresh())->get(route('staff.dashboard'))->assertForbidden();
        auth()->logout();
        $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'long-password-123'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'is_active' => 0]);
    }

    public function test_admin_creates_staff_and_resets_credentials(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $this->actingAs($admin)->post(route('admin.settings.user'), [
            'name' => 'Guidance Staff', 'email' => 'staff@example.test', 'password' => 'initial-secret-123', 'is_active' => '1',
        ])->assertRedirect();
        $staff = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertSame('staff', $staff->role);
        $this->assertTrue($staff->is_active);
        $this->actingAs($admin)->post(route('admin.settings.user', $staff), [
            'name' => 'Updated Staff', 'email' => $staff->email, 'password' => 'replacement-secret-123', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('replacement-secret-123', $staff->fresh()->password));
    }

    public function test_course_toggle_preserves_course_and_rejects_it_for_new_requests(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $course = Course::where('code', 'BSIT')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.settings.course', $course), [
            'code' => 'BSIT', 'name' => $course->name, 'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse($course->fresh()->is_active);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'code' => 'BSIT']);
        $validator = validator(['course' => 'BSIT'], ['course' => \App\Support\CourseCatalog::rule()]);
        $this->assertTrue($validator->fails());
    }

    public function test_new_program_becomes_available_to_requests(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $this->actingAs($admin)->post(route('admin.settings.course'), [
            'code' => 'BSN', 'name' => 'Bachelor of Science in Nursing', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('courses', ['code' => 'BSN', 'is_active' => 1]);
        $this->assertSame('Bachelor of Science in Nursing', \App\Support\CourseCatalog::activeOptions()['BSN']);
        $this->assertFalse(validator(['course' => 'BSN'], ['course' => \App\Support\CourseCatalog::rule()])->fails());
    }
}
