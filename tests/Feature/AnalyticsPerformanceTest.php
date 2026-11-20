<?php

namespace Tests\Feature;

use App\Http\Controllers\AnalyticsController;
use App\Models\Applicant;
use App\Models\GuidanceAppointment;
use App\Models\User;
use App\Services\AnalyticsDashboardService;
use App\Support\AnalyticsProfiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AnalyticsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedResponses(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $applicant = Applicant::create(['first_name' => 'Test', 'last_name' => 'Student', 'application_number' => uniqid('A')]);
            $appointment = GuidanceAppointment::create([
                'applicant_id' => $applicant->id, 'request_code' => uniqid('G'),
                'test_type' => 'phq9', 'student_status' => 'student', 'status' => 'Completed',
                'payment_slip_path' => '', 'origin_course' => 'BSIT',
            ]);
            $appointment->response()->create(['applicant_id' => $applicant->id, 'answers' => [],
                'score_summary' => ['test_type' => 'phq9', 'scores' => ['total' => 22]],
            ]);
        }
    }

    public function test_response_query_growth_and_totals(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $method = new \ReflectionMethod(AnalyticsController::class, 'compileExecutiveMetrics');
        $measure = function () use ($method) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $start = hrtime(true);
            $metrics = $method->invoke(app(AnalyticsController::class));
            $flags = app(AnalyticsDashboardService::class)->redFlagIntervention(5);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            return [$count, $metrics, $flags, (hrtime(true) - $start) / 1e6];
        };
        $this->seedResponses(10);
        [$small] = $measure();
        $this->seedResponses(30);
        [$large, $metrics, $flags, $ms] = $measure();
        fwrite(STDERR, "\nAnalytics benchmark: 10 responses={$small} queries; 40 responses={$large} queries, ".round($ms, 2)." ms\n");
        $this->assertSame(40, $metrics['redFlagsCount']);
        $this->assertSame(40, $metrics['scaleDistributions']['phq9_mood']['Severe']);
        $this->assertCount(5, $flags);
        $this->assertSame([40, 39, 38, 37, 36], array_column($flags, 'response_id'));
        $this->assertLessThanOrEqual($small + 2, $large, 'Queries must not grow per assessment response.');
    }

    public function test_intervention_limit_keeps_older_flags_after_a_page_of_normal_responses(): void
    {
        $this->seedResponses(1);
        $flagId = \App\Models\GuidanceTestResponse::first()->getKey();
        $this->seedResponses(105);
        \App\Models\GuidanceTestResponse::where('guidance_test_response_id', '>', $flagId)
            ->update(['score_summary' => json_encode(['test_type' => 'phq9', 'scores' => ['total' => 0]])]);
        $service = app(AnalyticsDashboardService::class);
        $this->assertSame([$flagId], array_column($service->redFlagIntervention(50), 'response_id'));
        $this->assertSame([], $service->redFlagIntervention(0));
    }

    public function test_profiler_reports_queries_and_restores_listener_after_failure(): void
    {
        Log::spy();
        $dispatcher = DB::connection()->getEventDispatcher();
        try {
            (new AnalyticsProfiler())->measure('test_stage', function () {
                DB::select('select 1');
                throw new \RuntimeException('test failure');
            });
            $this->fail('Expected operation failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('test failure', $exception->getMessage());
        }
        $this->assertSame($dispatcher, DB::connection()->getEventDispatcher());
        Log::shouldHaveReceived('info')->with('Analytics stage finished', \Mockery::on(fn ($context) =>
            $context['stage'] === 'test_stage' && $context['query_count'] === 1 && !$context['completed']
            && !str_contains(json_encode($context), 'select 1')
        ))->once();
    }
}
