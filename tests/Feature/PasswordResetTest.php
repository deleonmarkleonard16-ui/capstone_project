<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_links_to_password_recovery(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'))
            ->assertSee('Forgot password?');
    }

    public function test_user_can_request_a_password_reset_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password_with_a_valid_emailed_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password_setup_required' => true]);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ]);

            $response->assertRedirect(route('login'))->assertSessionHas('status');

            return Hash::check('new-secure-password', $user->fresh()->password)
                && ! $user->fresh()->password_setup_required;
        });
    }
}
