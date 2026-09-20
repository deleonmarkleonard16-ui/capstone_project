<?php

namespace App\Http\Controllers;

use App\Services\GuidanceAnalyticsService;
use App\Services\GuidanceAssessmentSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function index(Request $request, GuidanceAnalyticsService $analytics, GuidanceAssessmentSessionService $sessions)
    {
        $sessions->expireDue();

        $moduleKey = $request->route('module', 'psychological');
        if (!array_key_exists($moduleKey, GuidanceAnalyticsService::MODULE_SCALES)) {
            $moduleKey = 'psychological';
        }
        $module = \App\Http\Controllers\PsychologicalRequestController::MODULES[$moduleKey];
        $filters = $request->validate(['course' => ['nullable', Rule::in(array_keys(\App\Support\CourseCatalog::allOptions()))]]);
        $course = $filters['course'] ?? null;

        $data = [
            'moduleKey'     => $moduleKey,
            'module'        => $module,
            'distributions' => $analytics->distributions($moduleKey, $course),
            'totals'        => $analytics->totals($moduleKey, $course),
            'sectionTotals' => $analytics->sectionTotals($moduleKey, $course),
            'flags'         => $analytics->redFlags($moduleKey, $course)->paginate(20)->withQueryString(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['html' => view('guidance.analytics-data', $data)->render()]);
        }
        return view('guidance.analytics', $data);
    }
}
