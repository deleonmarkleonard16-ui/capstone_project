<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditActivity;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
}
