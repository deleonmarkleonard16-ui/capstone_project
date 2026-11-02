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
}
