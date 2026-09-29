<?php

namespace Tests\Feature;

use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Models\Role;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        foreach (['info' => 'Scheduled', 'warning' => 'In-Progress', 'success' => 'Completed'] as $color => $status) {
            $response->assertSee('<span class="badge text-bg-'.$color.'">'.$status.'</span>', false);
        }
    }

    public function test_recent_sessions_show_an_empty_state_without_admission_sessions(): void
    {
        $this->loginAsAdmin();

        $this->get('/admin/dashboard')->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->isEmpty())
            ->assertSee('No sessions yet.');
    }
}
