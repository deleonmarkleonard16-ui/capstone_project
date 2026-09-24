<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventBackHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BackHistoryProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_are_redirected_from_login_using_their_database_role(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->withSession(['user_role' => $role === 'admin' ? 'staff' : 'admin'])
                ->get('/login')->assertRedirect('/'.$role.'/analytics');
            $this->post('/login')->assertRedirect('/'.$role.'/analytics');
        }
    }

    public function test_login_and_authenticated_pages_are_not_cacheable_and_logout_blocks_return_visits(): void
    {
        $this->assertNoCache($this->get('/login')->assertOk());

        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->withSession(['user_role' => $role]);
            $page = $this->get('/'.$role.'/analytics')->assertOk();
            $this->assertNoCache($page);
            $this->assertNoCache($this->get('/'.$role.'/guidance-appointments')->assertOk());
            $page->assertSee('if (event.persisted) window.location.reload();', false);

            $logout = $this->post('/logout')->assertRedirect('/login')
                ->assertSessionMissing('user_role');
            $this->assertNoCache($logout);
            $this->assertGuest();
            $this->get('/'.$role.'/analytics')->assertRedirect('/login');
        }
    }

    public function test_every_authenticated_route_has_cache_protection(): void
    {
        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if (str_starts_with($route->uri(), 'admin/') || str_starts_with($route->uri(), 'staff/')) {
                $this->assertContains('auth', $middleware, $route->uri());
            }
            if (in_array('auth', $middleware, true)) {
                $this->assertContains(PreventBackHistory::class, $middleware, $route->uri());
            }
        }
    }

    private function assertNoCache($response): void
    {
        foreach (['no-cache', 'no-store', 'max-age=0', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $response->headers->get('Cache-Control'));
        }
        $response->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', 'Sun, 02 Jan 1990 00:00:00 GMT');
    }
}
