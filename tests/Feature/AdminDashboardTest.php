<?php

namespace Tests\Feature;

use App\Models\AdmissionCycle;
use App\Models\AdmissionApplicant;
use App\Models\AdmissionSession;
use App\Models\Role;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->create([
            'role_id' => Role::where('slug', 'admin')->value('id'),
        ]));
    }

    public function test_recent_sessions_show_the_latest_five_admission_sessions_and_roster_links(): void
    {
        $this->loginAsAdmin();
        $cycle = AdmissionCycle::create([
            'name' => '2026-2027',
            'academic_year' => '2026-2027',
        ]);
        $statuses = AdmissionSession::STATUSES;
        $sessions = collect();

        // Insert in reverse date order so insertion order cannot satisfy the query.
        foreach (range(6, 1) as $day) {
            $sessions->push(AdmissionSession::create([
                'admission_cycle_id' => $cycle->id,
                'session_name' => "Admission batch {$day}",
                'start_time' => "2026-09-0{$day} 08:30:00",
                'start_number' => 1,
                'end_number' => 50,
                'room' => "Admission room {$day}",
                'status' => $statuses[$day % 3],
            ]));
        }
        TestSession::create([
            'title' => 'Legacy session must not appear',
            'exam_date' => '2026-12-01',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'qr_token' => 'legacy-dashboard-session',
            'room' => 'Legacy room',
            'status' => 'scheduled',
        ]);

        $response = $this->get('/admin/dashboard')->assertOk();
        $expected = $sessions->take(5);
        $response->assertViewHas('sessions', function ($actual) use ($expected, $cycle) {
            return $actual->modelKeys() === $expected->pluck('id')->all()
                && $actual->every(fn ($session) => $session instanceof AdmissionSession
                    && $session->relationLoaded('cycle') && $session->cycle->is($cycle));
        });
        $response->assertViewHas('stats', fn ($stats) => $stats['sessions'] === 6)
            ->assertSeeInOrder($expected->pluck('session_name')->all())
            ->assertDontSee('Admission batch 1')
            ->assertDontSee('Legacy session must not appear')
            ->assertSee(route('admin.admission.sessions.index'), false)
            ->assertSee(route('admin.admission.sessions.create'), false);

        foreach ($expected as $session) {
            $response->assertSee($session->start_time->format('M d, Y h:i A'))
                ->assertSee($session->room)
                ->assertSee(route('admin.admission.sessions.show', $session), false);
        }
        foreach (['bg-secondary' => 'Scheduled', 'bg-warning text-dark' => 'In-Progress', 'bg-success' => 'Completed'] as $classes => $status) {
            $response->assertSee('<span class="badge '.$classes.'">'.$status.'</span>', false);
        }
    }

    public function test_recent_sessions_show_an_empty_state_without_admission_sessions(): void
    {
        $this->loginAsAdmin();

        $this->get('/admin/dashboard')->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->isEmpty())
            ->assertSee('No sessions yet.');
    }

    public function test_fully_submitted_session_keeps_its_stored_status_on_all_session_pages(): void
    {
        $this->loginAsAdmin();
        $cycle = AdmissionCycle::create([
            'name' => '2026-2027', 'academic_year' => '2026-2027',
            'status' => AdmissionCycle::STATUS_ACTIVE, 'is_active' => true,
        ]);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id, 'session_name' => 'Fully submitted batch',
            'start_time' => now(), 'start_number' => 1, 'end_number' => 1,
            'status' => AdmissionSession::STATUS_IN_PROGRESS,
        ]);
        $applicant = AdmissionApplicant::create([
            'admission_cycle_id' => $cycle->id, 'admission_session_id' => $session->id,
            'application_number' => 'STATUS-001', 'first_name' => 'Ana', 'last_name' => 'Cruz',
            'course_choice' => 'BSIT',
        ]);
        $applicant->forceFill(['submitted_at' => now()])->save();

        $this->assertTrue($session->allApplicantsSubmitted());
        $this->get('/admin/dashboard')->assertOk()
            ->assertSee('<span class="badge bg-warning text-dark">In-Progress</span>', false)
            ->assertDontSee('<span class="badge bg-success">Completed</span>', false);
        foreach (['show', 'index'] as $page) {
            $response = $this->get(route('admin.admission.sessions.'.$page, $page === 'show' ? $session : []))
                ->assertOk()->assertSee('bi-play-circle-fill me-1', false);
            $this->assertDoesNotMatchRegularExpression(
                '/<span class="badge bg-success(?: fs-6)?">\s*<i[^>]*><\/i> Completed/',
                $response->getContent()
            );
        }
        $this->assertSame(AdmissionSession::STATUS_IN_PROGRESS, $session->fresh()->status);
    }

    public function test_completion_paths_flush_cache_and_refresh_dashboard_status(): void
    {
        $this->loginAsAdmin();
        $cycle = AdmissionCycle::create([
            'name' => '2026-2027', 'academic_year' => '2026-2027',
            'status' => AdmissionCycle::STATUS_ACTIVE, 'is_active' => true,
        ]);

        foreach (['complete', 'status', 'update'] as $action) {
            $session = AdmissionSession::create([
                'admission_cycle_id' => $cycle->id, 'session_name' => "Completion via {$action}",
                'start_time' => now(), 'start_number' => 1, 'end_number' => 1,
                'status' => AdmissionSession::STATUS_IN_PROGRESS,
            ]);
            Cache::put('dashboard-status-regression', 'stale', 60);
            $payload = ['status' => AdmissionSession::STATUS_COMPLETED];
            if ($action === 'update') {
                $payload += [
                    'session_name' => $session->session_name,
                    'start_time' => $session->start_time->toDateTimeString(),
                    'start_number' => 1, 'end_number' => 1,
                ];
                $this->put(route('admin.admission.sessions.update', $session), $payload)->assertRedirect();
            } else {
                $this->post(route('admin.admission.sessions.'.$action, $session), $payload)->assertRedirect();
            }

            $this->assertSame(AdmissionSession::STATUS_COMPLETED, $session->fresh()->status);
            $this->assertNull(Cache::get('dashboard-status-regression'));
            $this->get('/admin/dashboard')->assertOk()
                ->assertSee('<span class="badge bg-success">Completed</span>', false)
                ->assertDontSee('<span class="badge bg-warning text-dark">In-Progress</span>', false);
        }
    }
}
