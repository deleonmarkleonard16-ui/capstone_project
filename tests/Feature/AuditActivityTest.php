<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditActivity;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(AuditActivity::class)->post('/_test/audit/progress', fn () => response()->json(['ok' => true]))
            ->name('guidance.progress');
        Route::middleware(AuditActivity::class)->post('/_test/audit/submit', fn () => response()->json(['ok' => true]))
            ->name('guidance.submit');
    }

    public function test_background_progress_requests_are_not_audited(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->postJson('/_test/audit/progress')->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_meaningful_submit_requests_are_audited(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->postJson('/_test/audit/submit')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $staff->id,
            'route' => 'guidance.submit',
            'action' => 'Guidance submit',
        ]);
    }

    #[DataProvider('unsuccessfulRequests')]
    public function test_denied_and_failed_responses_are_audited(string $method, int $status): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Route::middleware(AuditActivity::class)->match([$method], '/_test/audit/error', fn () => response('', $status))
            ->name('audit.error');

        $this->actingAs($admin)->json($method, '/_test/audit/error')->assertStatus($status);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'route' => 'audit.error',
            'method' => $method,
            'response_status' => $status,
        ]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public static function unsuccessfulRequests(): array
    {
        return [
            'unauthorized' => ['POST', 401],
            'forbidden' => ['POST', 403],
            'validation error' => ['PATCH', 422],
            'server error' => ['DELETE', 500],
            'denied page access' => ['GET', 403],
        ];
    }

    public function test_role_denials_are_audited_and_visible_in_the_denied_filter(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('admin.audit-logs'))->assertForbidden();

        $log = AuditLog::sole();
        $this->assertSame(403, $log->response_status);
        $this->assertSame($staff->id, $log->user_id);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.audit-logs', ['status' => 'denied']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$log->id]);
    }

    public function test_unhandled_errors_are_audited_and_visible_in_the_failed_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Route::middleware(AuditActivity::class)->post('/_test/audit/exception', function () {
            throw new \RuntimeException('Test server error');
        })->name('audit.exception');

        $this->actingAs($admin)->postJson('/_test/audit/exception')->assertStatus(500);

        $log = AuditLog::sole();
        $this->assertSame(500, $log->response_status);
        $this->actingAs($admin)->get(route('admin.audit-logs', ['status' => 'failed']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$log->id]);
        $this->get(route('admin.audit-logs', ['status' => 'success']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->isEmpty());
    }

    public function test_successful_page_views_are_not_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.audit-logs'))->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('redirectFailures')]
    public function test_redirect_failures_are_audited_without_changing_the_redirect(string $kind, int $status): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Route::middleware('web')->post('/_test/audit/redirect', function (\Illuminate\Http\Request $request) use ($kind) {
            return match ($kind) {
                'validation' => $request->validate(['required_value' => 'required']),
                'errors' => redirect('/_test/audit/landing')->withErrors(['field' => 'Invalid value']),
                'error' => redirect('/_test/audit/landing')->with('error', 'Operation failed'),
                'warning' => redirect('/_test/audit/landing')->with('warning', 'Operation blocked'),
                'expired' => abort(419),
            };
        })->name('audit.redirect');

        $this->actingAs($admin)->post('/_test/audit/redirect')->assertRedirect();

        $this->assertSame($status, AuditLog::sole()->response_status);
    }

    public static function redirectFailures(): array
    {
        return [
            'validation exception' => ['validation', 422],
            'validation bag' => ['errors', 422],
            'caught failure' => ['error', 422],
            'blocked operation' => ['warning', 409],
            'expired session' => ['expired', 419],
        ];
    }

    public function test_old_flashed_errors_do_not_mark_the_next_success_as_failed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Route::middleware('web')->post('/_test/audit/flash-error', fn () => redirect('/')->with('error', 'Failed'))
            ->name('audit.flash-error');
        Route::middleware('web')->post('/_test/audit/flash-success', fn () => redirect('/')->with('success', 'Saved'))
            ->name('audit.flash-success');

        $this->actingAs($admin)->post('/_test/audit/flash-error')->assertRedirect();
        $this->post('/_test/audit/flash-success')->assertRedirect();

        $this->assertSame(422, AuditLog::where('route', 'audit.flash-error')->sole()->response_status);
        $this->assertSame(302, AuditLog::where('route', 'audit.flash-success')->sole()->response_status);
    }

    public function test_failed_background_requests_are_visible_in_audit_filters_and_export(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Route::post('/_test/audit/background-failed', fn () => response()->json(['error' => 'Denied'], 403))
            ->name('answers.save-progress');

        $this->actingAs($admin)->postJson('/_test/audit/background-failed')->assertForbidden();

        $log = AuditLog::sole();
        $this->assertSame(403, $log->response_status);
        $this->get(route('admin.audit-logs', ['status' => 'denied']))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->contains('id', $log->id));
        $export = $this->get(route('admin.audit-logs', ['status' => 'denied', 'export' => 'csv']))->assertOk();
        $this->assertStringContainsString('answers.save-progress', $export->streamedContent());
    }

    public function test_json_failure_with_a_success_http_status_is_audited_as_failed(): void
    {
        Route::post('/_test/audit/json-failed', fn () => response()->json(['success' => false]));

        $this->postJson('/_test/audit/json-failed')->assertOk();

        $this->assertSame(422, AuditLog::sole()->response_status);
        $this->assertSame('guest', AuditLog::sole()->user_role);
    }

    public function test_unauthenticated_redirect_is_audited_as_denied(): void
    {
        $this->get(route('admin.audit-logs'))->assertRedirect(route('login'));

        $log = AuditLog::sole();
        $this->assertSame(401, $log->response_status);
        $this->assertNull($log->user_id);
        $this->assertSame('guest', $log->user_role);
    }

    public function test_unmatched_routes_are_logged_and_visible_without_recording_url_secrets(): void
    {
        $this->get('/_test/missing/private-token-value')->assertNotFound();

        $log = AuditLog::sole();
        $this->assertSame(404, $log->response_status);
        $this->assertStringNotContainsString('private-token-value', $log->action);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.audit-logs', ['status' => 'failed', 'role' => 'guest']))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->contains('id', $log->id));
    }

    public function test_failed_login_is_denied_and_does_not_record_credentials(): void
    {
        $this->post(route('login.store'), ['email' => 'unknown@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $log = AuditLog::sole();
        $this->assertSame(401, $log->response_status);
        $this->assertStringNotContainsString('secret-password', $log->toJson());
        $this->assertStringNotContainsString('unknown@example.com', $log->toJson());
    }
}
