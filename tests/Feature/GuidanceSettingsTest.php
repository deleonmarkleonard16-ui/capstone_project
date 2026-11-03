<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
            ->assertSee('Course / Program Management')->assertSee('Account Management')
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

    public function test_admin_creates_staff_but_cannot_reset_their_credentials(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'password' => 'admin-password']);
        $this->actingAs($admin)->post(route('admin.settings.user'), [
            'name' => 'Guidance Staff', 'email' => 'staff@example.test', 'role' => 'staff',
            'admin_password' => 'admin-password',
        ])->assertRedirect();
        $staff = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertSame('staff', $staff->role);
        $this->assertTrue($staff->is_active);
        $this->assertTrue($staff->password_setup_required);
        Mail::assertSent(\App\Mail\AccountCreatedMail::class, fn ($mail) => $mail->hasTo('staff@example.test'));
        $originalPassword = $staff->password;
        $this->actingAs($admin)->post(route('admin.settings.user', $staff), [
            'name' => 'Updated Staff', 'email' => $staff->email, 'password' => 'replacement-secret-123', 'is_active' => '1',
        ])->assertForbidden();
        $this->assertSame($originalPassword, $staff->fresh()->password);
        $this->assertSame('Guidance Staff', $staff->fresh()->name);
    }

    public function test_new_admin_cannot_change_staff_or_other_admin_credentials(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'admin-password']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($admin)->post(route('admin.settings.user'), [
            'name' => 'New Admin', 'email' => 'new-admin@example.test', 'role' => 'admin',
            'admin_password' => 'admin-password',
        ])->assertRedirect()->assertSessionHas('success', 'Admin account approved. Login credentials were sent to new-admin@example.test.');
        $newAdmin = User::where('email', 'new-admin@example.test')->firstOrFail();

        foreach ([$admin, $staff] as $target) {
            $originalPassword = $target->password;
            $this->actingAs($newAdmin)->post(route('admin.settings.user', $target), [
                'name' => $target->name, 'email' => $target->email, 'is_active' => '1',
                'password' => 'replacement-secret-123',
            ])->assertForbidden();
            $this->assertSame($originalPassword, $target->fresh()->password);

            $originalEmail = $target->email;
            $this->actingAs($newAdmin)->post(route('admin.settings.user', $target), [
                'name' => 'Changed Name', 'email' => 'changed-email@example.test', 'is_active' => '0',
            ])->assertForbidden();
            $this->assertSame($originalEmail, $target->fresh()->email);
            $this->assertSame($target->name, $target->fresh()->name);
            $this->assertTrue($target->fresh()->is_active);
        }

        $response = $this->actingAs($newAdmin)->get(route('admin.settings.index', ['tab' => 'staff']))
            ->assertOk()->assertDontSee('Brevo')->assertDontSee('Reset Password');
        $this->assertSame(1, substr_count($response->getContent(), 'name="password"'));
        foreach ([$admin, $staff] as $target) {
            $response->assertSee('value="'.$target->email.'" class="form-control form-control-sm" readonly required', false);
        }
        $response->assertSee('value="'.$newAdmin->email.'" class="form-control form-control-sm"  required', false);
    }

    public function test_admin_can_change_their_own_password_and_edit_staff_without_resetting_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $originalPassword = $staff->password;
        $this->actingAs($admin)->post(route('admin.settings.user', $staff), [
            'name' => 'Updated Staff', 'email' => $staff->email, 'is_active' => '1', 'password' => '',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Updated Staff', $staff->fresh()->name);
        $this->assertSame($originalPassword, $staff->fresh()->password);

        $this->post(route('admin.settings.user', $admin), [
            'name' => $admin->name, 'email' => 'updated-admin@example.test', 'is_active' => '1',
            'password' => 'replacement-secret-123',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('replacement-secret-123', $admin->fresh()->password));
        $this->assertSame('updated-admin@example.test', $admin->fresh()->email);
    }

    public function test_account_email_does_not_double_encode_temporary_password(): void
    {
        $account = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $html = (new \App\Mail\AccountCreatedMail($account, 'Example&Password'))->render();

        $this->assertStringContainsString('Example&amp;Password', $html);
        $this->assertStringNotContainsString('Example&amp;amp;Password', $html);
    }

    public function test_failed_brevo_delivery_removes_inactive_account_and_allows_retry(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'password' => 'admin-password',
        ]);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP timed out'));

        $this->actingAs($admin)->post(route('admin.settings.user'), [
            'name' => 'Retry Staff',
            'email' => 'retry@example.test',
            'role' => 'staff',
            'admin_password' => 'admin-password',
        ])->assertRedirect()->assertSessionHas('error', 'The email could not be sent to retry@example.test. The account was not created. Please try again.');

        $this->assertDatabaseMissing('users', ['email' => 'retry@example.test']);
    }

    public function test_admin_can_delete_another_staff_account(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);

        $this->actingAs($admin)
            ->delete(route('admin.settings.user.destroy', $staff))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);

        $this->actingAs($admin)
            ->delete(route('admin.settings.user.destroy', $admin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_admin_when_one_will_remain(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $otherAdmin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);

        $this->actingAs($admin)
            ->delete(route('admin.settings.user.destroy', $otherAdmin))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $otherAdmin->id]);
    }

    public function test_official_program_cannot_be_disabled(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $course = Course::where('code', 'BSIT')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.settings.course', $course), [
            'code' => 'BSIT', 'name' => $course->name, 'is_active' => '0',
        ])->assertUnprocessable();
        $this->assertTrue($course->fresh()->is_active);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'code' => 'BSIT']);
        $validator = validator(['course' => 'BSIT'], ['course' => \App\Support\CourseCatalog::rule()]);
        $this->assertFalse($validator->fails());
    }

    public function test_unofficial_program_is_rejected(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
        $this->actingAs($admin)->post(route('admin.settings.course'), [
            'code' => 'BSN', 'name' => 'Bachelor of Science in Nursing', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertDatabaseMissing('courses', ['code' => 'BSN']);
        $this->assertCount(10, \App\Support\CourseCatalog::activeOptions());
        $this->assertTrue(validator(['course' => 'BSN'], ['course' => \App\Support\CourseCatalog::rule()])->fails());
    }
}
