<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\Applicant;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceSetting;
use App\Models\GuidanceTestResponse;
use App\Models\ServiceRequest;
use App\Services\AnalyticsDashboardService;
use App\Services\GuidanceAnalyticsService;
use App\Services\GuidanceAssessmentSessionService;
use App\Support\CourseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    /** Use the same branded renderer for institutional reports and record exports. */
    public function exportReport(Request $request)
    {
        $filters = $request->validate([
            'format' => ['nullable', Rule::in(['html', 'pdf', 'docx', 'csv'])],
            'type' => ['nullable', Rule::in(['summary', 'records'])],
            'cycle_id' => ['nullable', 'integer', 'exists:admission_cycles,id'],
            'auto_print' => ['nullable', 'boolean'],
            'course' => ['prohibited'], 'status' => ['prohibited'],
        ]);
        $format = $filters['format'] ?? 'html';
        $fallback = route($request->user()->role.'.exports.index');
        try {
            $cycleId = isset($filters['cycle_id']) ? (int) $filters['cycle_id'] : null;
            $metrics = $this->compileExecutiveMetrics($cycleId);
            $dashboard = app(AnalyticsDashboardService::class);
            $metrics['actionCenter'] = $dashboard->actionCenterCounts();
            $metrics['documentFees'] = $dashboard->documentFeeAnalytics();
            $records = collect();
            $columns = ['Metric', 'Value'];
            if (($filters['type'] ?? 'summary') === 'records') {
                $columns = ['Reference', 'Name', 'Student ID', 'Gender', 'Course', 'Service / Assessment', 'O.R. Number', 'O.R. Date', 'Status', 'Intervention Status', 'Scores / Interpretation', 'Date'];
                foreach (GuidanceAppointment::with(['applicant', 'serviceRequest', 'response', 'batch', 'sourceBatch'])->orderBy('guidance_appointment_id')->get() as $r) {
                    $records->push([$r->reference ?: '-', trim(($r->last_name ?? '').', '.($r->first_name ?? '').' '.($r->middle_name ?? ''), ' ,') ?: 'N/A', $r->student_number ?? '-', $r->applicant?->gender ?? $r->serviceRequest?->gender ?? '-', $r->courseLabel(), $r->testLabel(), $r->or_number ?? $r->serviceRequest?->or_number ?? '-', $r->or_date?->format('Y-m-d') ?? $r->serviceRequest?->or_date?->format('Y-m-d') ?? '-', $r->status ?? '-', $r->counseling_status ?? 'Pending Review', $this->exportMetricText($r->response?->testSummaries() ?? []), $r->created_at?->format('Y-m-d') ?? '-']);
                }
                foreach (ServiceRequest::whereIn('service', ['good-moral', 'exit-form'])->get() as $r) {
                    $records->push([$r->reference ?? '-', trim(($r->last_name ?? '').', '.($r->first_name ?? ''), ' ,') ?: 'N/A', $r->student_number ?: '-', $r->gender ?? '-', $r->courseLabel(), ServiceRequest::SERVICES[$r->service] ?? 'Document', $r->or_number ?? '-', $r->or_date?->format('Y-m-d') ?? '-', $r->status ?? '-', 'N/A', $r->purpose ?: '-', $r->created_at?->format('Y-m-d') ?? '-']);
                }
                foreach (AdmissionApplicant::when($cycleId, fn ($q, $id) => $q->where('admission_cycle_id', $id))->get() as $r) {
                    $records->push([$r->application_number ?? '-', $r->full_name ?? 'N/A', $r->student_id ?? '-', $r->sex ?? '-', $r->course_choice ?? 'N/A', 'Admission', 'N/A', 'N/A', $r->qualification_status ?? 'Pending', 'N/A', 'GWA: '.($r->gwa ?? '-').'; Exam: '.($r->exam_score ?? '-').'; Total: '.($r->total_score ?? '-'), $r->created_at?->format('Y-m-d') ?? '-']);
                }
            } elseif (($metrics['totalRequests'] ?? 0) > 0) {
                $appendMetric = function (string $label, $value) use (&$appendMetric, $records): void {
                    if (is_array($value) && $value !== []) {
                        foreach ($value as $key => $child) $appendMetric($label.' / '.\Illuminate\Support\Str::headline((string) $key), $child);
                    } else {
                        $records->push([$label, is_array($value) ? '-' : (string) ($value ?? '-')]);
                    }
                };
                foreach ($metrics as $key => $value) {
                    if (in_array($key, ['guidanceOnly', 'role', 'officialPrograms'], true)) continue;
                    $appendMetric(\Illuminate\Support\Str::headline($key), $value);
                }
            }
            if ($records->isEmpty()) return redirect($fallback)->with('error', 'No eligible records found for the selected filters.');
            $document = [
                'title' => ($filters['type'] ?? 'summary') === 'records' ? 'Institutional Administrative Records Report' : 'Executive Institutional Analytics Summary',
                'office' => 'Guidance and Counseling Office / Testing and Admission Office',
                'metadata' => ['Admission Cycle' => $cycleId ? (AdmissionCycle::find($cycleId)?->display_name ?? 'N/A') : 'All admission cycles', 'Academic Year' => GuidanceSetting::valueOf('academic_year', date('Y').'-'.(date('Y') + 1)), 'Guidance / Documents' => 'All batches and requests', 'Date Generated' => now()->timezone('Asia/Manila')->format('F j, Y g:i A').' (Asia/Manila)', 'Active Filters' => 'Admission cycle: '.($cycleId ?? 'All').'; Guidance / Documents: All'],
                'summary' => [], 'certificates' => [], 'orientation' => 'landscape', 'columns' => $columns, 'rows' => $records,
                'format' => $format, 'autoPrint' => (bool) ($filters['auto_print'] ?? false),
            ];
            $bytes = app(\App\Services\DocumentExportService::class)->render($document, $format);
            $mime = match ($format) { 'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'csv' => 'text/csv; charset=UTF-8', default => 'text/html; charset=UTF-8' };
            $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store'];
            if ($format !== 'html') $headers['Content-Disposition'] = 'attachment; filename="DMSGTA-institutional-report-'.now()->format('Y-m-d').'.'.$format.'"';

            return response($bytes, 200, $headers);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Institutional export failed', ['exception' => $e]);

            return redirect($fallback)->with('error', 'Unable to generate the document. Please contact the system administrator or try another format.');
        }
    }

    private function exportMetricText(array $values, string $prefix = ''): string
    {
        $parts = [];
        foreach ($values as $key => $value) {
            $label = $prefix.\Illuminate\Support\Str::headline((string) $key);
            $parts[] = is_array($value) ? $this->exportMetricText($value, $label.' / ') : $label.': '.(string) ($value ?? '-');
        }

        return implode('; ', $parts) ?: '-';
    }

    /**
     * Sub-module tab analytics (Psychological, Personality, Career).
     */
    public function index(Request $request, GuidanceAnalyticsService $analytics, GuidanceAssessmentSessionService $sessions)
    {
        $sessions->expireDue();

        $moduleKey = $request->route('module', 'psychological');
        if (!array_key_exists($moduleKey, GuidanceAnalyticsService::MODULE_SCALES)) {
            $moduleKey = 'psychological';
        }
        $module = \App\Http\Controllers\PsychologicalRequestController::MODULES[$moduleKey];
        $filters = $request->validate(['course' => ['nullable', Rule::in(array_keys(CourseCatalog::allOptions()))]]);
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

    /**
     * Main Executive Analytics Dashboard Engine.
     */
    public function dashboard(Request $request, AnalyticsDashboardService $dashboardService)
    {
        app(GuidanceAssessmentSessionService::class)->expireDue();

        $selectedCycleId = $request->query('cycle_id') ? (int) $request->query('cycle_id') : null;
        $metrics = $this->compileExecutiveMetrics($selectedCycleId);

        // Merge service datasets: Action Center, Intervention, Proctoring Feed, Demographics, Document/Fee Analytics, Controls
        $metrics['actionCenter']       = $dashboardService->actionCenterCounts();
        $metrics['interventionFlags']  = $dashboardService->redFlagIntervention(50);
        $metrics['proctoring']         = $dashboardService->proctoringFeed();
        $metrics['demographics']       = $dashboardService->demographicAnalytics();
        $metrics['documentFees']       = $dashboardService->documentFeeAnalytics();
        $metrics['controls']           = $dashboardService->systemControls();
        $metrics['selectedCycleId']    = $selectedCycleId ?: ($metrics['controls']['activeCycleId'] ?? null);

        return view('admin.analytics', $metrics);
    }

    /**
     * Fetch Confidential Student Profile JSON breakdown for secure modal rendering.
     */
    public function confidentialProfile(int $responseId, AnalyticsDashboardService $dashboardService): JsonResponse
    {
        $data = $dashboardService->getConfidentialProfile($responseId);
        if (!$data) {
            return response()->json(['error' => 'Confidential profile record not found.'], 404);
        }

        return response()->json($data);
    }

    /**
     * Update Student Counseling / Intervention Status.
     */
    public function updateIntervention(Request $request, GuidanceAppointment $appointment, AnalyticsDashboardService $dashboardService): JsonResponse
    {
        $validated = $request->validate([
            'counseling_status' => ['required', 'string', Rule::in(['Pending Review', 'Counseling Scheduled', 'In Progress', 'Completed / Resolved', 'Declined'])],
            'counseling_notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        $dashboardService->updateIntervention($appointment, $validated['counseling_status'], $validated['counseling_notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Student counseling intervention status updated to ' . $validated['counseling_status'] . '.',
            'status'  => $validated['counseling_status'],
        ]);
    }

    /**
     * Staff remote proctoring action (Warn, Pause, Force Terminate).
     */
    public function proctorAction(Request $request, GuidanceAppointment $appointment, AnalyticsDashboardService $dashboardService): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['warn', 'pause', 'force_terminate'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $dashboardService->executeProctorAction($appointment, $validated['action'], $validated['reason'] ?? null);

        return response()->json($result);
    }

    /**
     * Toggle campus-wide emergency proctor override (pauses active test timers).
     */
    public function toggleProctorOverride(Request $request, AnalyticsDashboardService $dashboardService): JsonResponse
    {
        $isPaused = $dashboardService->toggleProctorOverride();

        return response()->json([
            'success'          => true,
            'proctoringPaused' => $isPaused,
            'message'          => $isPaused ? 'Campus-wide emergency proctor override ENABLED. Active test timers paused.' : 'Emergency proctor override DISABLED. Timers resumed.',
        ]);
    }

    /**
     * Live stats polling endpoint for asynchronous dashboard sync.
     */
    public function liveStats(AnalyticsDashboardService $dashboardService): JsonResponse
    {
        return response()->json([
            'actionCenter' => $dashboardService->actionCenterCounts(),
            'proctoring'   => $dashboardService->proctoringFeed(),
            'controls'     => $dashboardService->systemControls(),
            'timestamp'    => now()->timezone('Asia/Manila')->format('h:i:s A'),
        ]);
    }

    /**
     * Export Executive Analytics Summary as PDF.
     */
    public function exportPdf(Request $request, AnalyticsDashboardService $dashboardService)
    {
        $request->merge(['format' => 'pdf', 'type' => 'summary']);

        return $this->exportReport($request);
    }

    /**
     * Export Executive Analytics Data as Excel-compatible CSV.
     */
    public function exportExcel(Request $request)
    {
        $request->merge(['format' => 'csv', 'type' => 'records']);

        return $this->exportReport($request);
    }

    /**
     * Compile comprehensive institutional analytics metrics across all 3 modules.
     */
    private function compileExecutiveMetrics(?int $cycleId = null): array
    {
        // ── 1. Admission Module Analytics ──
        $admissionQuery = AdmissionApplicant::query();
        if ($cycleId) {
            $admissionQuery->where('admission_cycle_id', $cycleId);
        }

        $totalAdmissionApplicants = (clone $admissionQuery)->count();
        $qualifiedAdmissionCount = (clone $admissionQuery)->where('qualification_status', 'Qualified')->count();
        $notQualifiedAdmissionCount = (clone $admissionQuery)->where('qualification_status', 'Not Qualified')->count();
        $pendingAdmissionCount = (clone $admissionQuery)->where(function ($q) {
            $q->whereNull('qualification_status')
              ->orWhere('qualification_status', 'Pending')
              ->orWhere('qualification_status', '');
        })->count();

        $admissionStatusBreakdown = [
            'Qualified'     => $qualifiedAdmissionCount,
            'Not Qualified' => $notQualifiedAdmissionCount,
            'Pending'       => $pendingAdmissionCount,
        ];

        // ── 2. Request Testing Services Analytics ──
        $psychologicalRequestsCount = ServiceRequest::where('service', 'psychological')->count()
            + GuidanceAppointment::where(function ($q) {
                $q->where('test_types', 'like', '%psychological%')
                  ->orWhere('test_types', 'like', '%dass21%')
                  ->orWhere('test_types', 'like', '%phq9%')
                  ->orWhere('test_types', 'like', '%gad7%')
                  ->orWhereNull('test_types');
            })->count();

        $personalityRequestsCount = ServiceRequest::where('service', 'personality')->count()
            + GuidanceAppointment::where(function ($q) {
                $q->where('test_types', 'like', '%personality%')
                  ->orWhere('test_types', 'like', '%bfpi%');
            })->count();

        $careerRequestsCount = ServiceRequest::where('service', 'career')->count()
            + GuidanceAppointment::where('test_types', 'like', '%career%')->count();

        $testingServicesTotal = $psychologicalRequestsCount + $personalityRequestsCount + $careerRequestsCount;
        $completedTests = GuidanceAppointment::where('status', 'Completed')->count();

        // Psychometric Severity Distributions & Red Flags
        $responses = GuidanceTestResponse::with(['appointment.serviceRequest', 'appointment.applicant'])->get();

        $severityDistribution = [
            'Normal / Minimal' => 0,
            'Mild'             => 0,
            'Moderate'         => 0,
            'Severe'           => 0,
            'Extremely Severe' => 0,
        ];

        $scaleDistributions = [
            'dass21_depression' => ['Normal' => 0, 'Mild' => 0, 'Moderate' => 0, 'Severe' => 0, 'Extremely Severe' => 0],
            'dass21_anxiety'    => ['Normal' => 0, 'Mild' => 0, 'Moderate' => 0, 'Severe' => 0, 'Extremely Severe' => 0],
            'dass21_stress'     => ['Normal' => 0, 'Mild' => 0, 'Moderate' => 0, 'Severe' => 0, 'Extremely Severe' => 0],
            'phq9_mood'         => ['Minimal' => 0, 'Mild' => 0, 'Moderate' => 0, 'Moderately Severe' => 0, 'Severe' => 0],
            'gad7_anxiety'      => ['Minimal' => 0, 'Mild' => 0, 'Moderate' => 0, 'Severe' => 0],
        ];

        $personalityTraits = [
            'extraversion'       => ['Low' => 0, 'Average' => 0, 'High' => 0],
            'agreeableness'      => ['Low' => 0, 'Average' => 0, 'High' => 0],
            'conscientiousness' => ['Low' => 0, 'Average' => 0, 'High' => 0],
            'neuroticism'        => ['Low' => 0, 'Average' => 0, 'High' => 0],
            'openness'           => ['Low' => 0, 'Average' => 0, 'High' => 0],
        ];

        $redFlags = [];

        foreach ($responses as $response) {
            $summaries = $response->testSummaries();
            $appointment = $response->appointment;
            $applicant = $response->applicant ?? $appointment?->applicant;
            $serviceRequest = $appointment?->serviceRequest;

            $studentName = mb_strtoupper($applicant?->full_name ?? ($serviceRequest ? trim($serviceRequest->first_name . ' ' . $serviceRequest->last_name) : 'Anonymous Examinee'));
            $studentId = $appointment?->student_number ?: ($applicant?->application_number ?: ($serviceRequest?->student_number ?: 'Not Provided'));
            $course = $appointment ? $appointment->courseLabel() : ($serviceRequest?->courseLabel() ?? 'Unspecified');

            // DASS-21
            if (isset($summaries['dass21']['interpretation'])) {
                $dass = $summaries['dass21']['interpretation'];
                foreach (['depression', 'anxiety', 'stress'] as $dim) {
                    if (isset($dass[$dim])) {
                        $sev = $dass[$dim];
                        if (isset($scaleDistributions['dass21_' . $dim][$sev])) {
                            $scaleDistributions['dass21_' . $dim][$sev]++;
                        }
                        if (in_array($sev, ['Normal', 'Minimal'], true)) $severityDistribution['Normal / Minimal']++;
                        elseif (in_array($sev, ['Mild', 'Moderate', 'Severe', 'Extremely Severe'], true)) $severityDistribution[$sev]++;

                        if (in_array($sev, ['Severe', 'Extremely Severe'], true)) {
                            $redFlags[] = [
                                'name'         => $studentName,
                                'student_id'   => $studentId,
                                'course'       => $course,
                                'scale'        => 'DASS-21 ' . ucfirst($dim),
                                'severity'     => $sev,
                                'date'         => $response->created_at ? $response->created_at->timezone('Asia/Manila')->format('M d, Y') : 'N/A',
                                'view_url'     => $appointment ? route(auth()->user()->role . '.guidance-appointments.show-results', $appointment) : '#',
                            ];
                        }
                    }
                }
            }

            // PHQ-9
            if (isset($summaries['phq9']['interpretation']['severity'])) {
                $phqSev = $summaries['phq9']['interpretation']['severity'];
                if (isset($scaleDistributions['phq9_mood'][$phqSev])) {
                    $scaleDistributions['phq9_mood'][$phqSev]++;
                }
                if ($phqSev === 'Minimal') $severityDistribution['Normal / Minimal']++;
                elseif ($phqSev === 'Moderately Severe') $severityDistribution['Severe']++;
                elseif (isset($severityDistribution[$phqSev])) $severityDistribution[$phqSev]++;

                if (in_array($phqSev, ['Moderately Severe', 'Severe'], true)) {
                    $redFlags[] = [
                        'name'         => $studentName,
                        'student_id'   => $studentId,
                        'course'       => $course,
                        'scale'        => 'PHQ-9 Mood Assessment',
                        'severity'     => $phqSev,
                        'date'         => $response->created_at ? $response->created_at->timezone('Asia/Manila')->format('M d, Y') : 'N/A',
                        'view_url'     => $appointment ? route(auth()->user()->role . '.guidance-appointments.show-results', $appointment) : '#',
                    ];
                }
            }

            // GAD-7
            if (isset($summaries['gad7']['interpretation']['severity'])) {
                $gadSev = $summaries['gad7']['interpretation']['severity'];
                if (isset($scaleDistributions['gad7_anxiety'][$gadSev])) {
                    $scaleDistributions['gad7_anxiety'][$gadSev]++;
                }
                if ($gadSev === 'Minimal') $severityDistribution['Normal / Minimal']++;
                elseif (isset($severityDistribution[$gadSev])) $severityDistribution[$gadSev]++;

                if ($gadSev === 'Severe') {
                    $redFlags[] = [
                        'name'         => $studentName,
                        'student_id'   => $studentId,
                        'course'       => $course,
                        'scale'        => 'GAD-7 Generalized Anxiety',
                        'severity'     => 'Severe',
                        'date'         => $response->created_at ? $response->created_at->timezone('Asia/Manila')->format('M d, Y') : 'N/A',
                        'view_url'     => $appointment ? route(auth()->user()->role . '.guidance-appointments.show-results', $appointment) : '#',
                    ];
                }
            }

            // BFPI (Big Five)
            if (isset($summaries['bfpi']['interpretation'])) {
                foreach ($summaries['bfpi']['interpretation'] as $trait => $level) {
                    $traitKey = strtolower($trait);
                    $levelKey = ucfirst(strtolower($level));
                    if (isset($personalityTraits[$traitKey][$levelKey])) {
                        $personalityTraits[$traitKey][$levelKey]++;
                    }
                }
            }
        }

        $redFlagsCount = count($redFlags);

        // Program Testing Volume (All campus degree programs dynamically merged)
        $officialPrograms = CourseCatalog::allOptions();
        $programTestingVolume = [];
        $admissionByProgram = [];

        foreach ($officialPrograms as $code => $title) {
            $programTestingVolume[$code] = 0;
            $admissionByProgram[$code] = [
                'code'      => $code,
                'title'     => $title,
                'total'     => 0,
                'qualified' => 0,
            ];
        }

        // Count across ServiceRequest
        $srCourses = ServiceRequest::selectRaw('course, COUNT(*) as total')
            ->whereNotNull('course')
            ->groupBy('course')
            ->pluck('total', 'course')
            ->toArray();

        foreach ($srCourses as $courseCode => $count) {
            $norm = CourseCatalog::normalizeLegacy($courseCode) ?? $courseCode;
            if (isset($programTestingVolume[$norm])) {
                $programTestingVolume[$norm] += $count;
            }
        }

        // Count across GuidanceAppointment origin_course / batch
        $gaCourses = GuidanceAppointment::whereNull('service_request_id')
            ->selectRaw('origin_course, COUNT(*) as total')
            ->whereNotNull('origin_course')
            ->groupBy('origin_course')
            ->pluck('total', 'origin_course')
            ->toArray();

        foreach ($gaCourses as $courseCode => $count) {
            $norm = CourseCatalog::normalizeLegacy($courseCode) ?? $courseCode;
            if (isset($programTestingVolume[$norm])) {
                $programTestingVolume[$norm] += $count;
            }
        }

        // Admission counts per program (filtered by cycle if provided)
        $admCourseStats = (clone $admissionQuery)
            ->selectRaw('course_choice, COUNT(*) as total, SUM(CASE WHEN qualification_status = "Qualified" THEN 1 ELSE 0 END) as qualified')
            ->whereNotNull('course_choice')
            ->groupBy('course_choice')
            ->get();

        foreach ($admCourseStats as $cs) {
            $code = $cs->course_choice;
            if (isset($admissionByProgram[$code])) {
                $admissionByProgram[$code]['total'] = (int) $cs->total;
                $admissionByProgram[$code]['qualified'] = (int) $cs->qualified;
            }
        }

        // ── 3. Document Services & Clearances Analytics ──
        $goodMoralTotal = ServiceRequest::where('service', 'good-moral')->count();
        $goodMoralClaimed = ServiceRequest::where('service', 'good-moral')->whereIn('status', ['completed', 'claimed', 'ready'])->count();
        $goodMoralPending = ServiceRequest::where('service', 'good-moral')->whereIn('status', ['pending', 'approved', 'proof_review'])->count();

        $exitFormTotal = ServiceRequest::where('service', 'exit-form')->count();
        $exitFormClaimed = ServiceRequest::where('service', 'exit-form')->whereIn('status', ['completed', 'claimed', 'ready'])->count();
        $exitFormPending = ServiceRequest::where('service', 'exit-form')->whereIn('status', ['pending', 'approved', 'proof_review'])->count();

        // ── 4. Socio-Demographics & Monthly Trends ──
        $maleCount = ServiceRequest::whereRaw('LOWER(gender) = ?', ['male'])->count()
            + Applicant::whereHas('genderLookup', fn ($q) => $q->where('slug', 'male'))->count()
            + AdmissionApplicant::whereRaw('LOWER(sex) = ?', ['male'])->count();

        $femaleCount = ServiceRequest::whereRaw('LOWER(gender) = ?', ['female'])->count()
            + Applicant::whereHas('genderLookup', fn ($q) => $q->where('slug', 'female'))->count()
            + AdmissionApplicant::whereRaw('LOWER(sex) = ?', ['female'])->count();

        if ($maleCount === 0 && $femaleCount === 0) {
            $maleCount = 1;
            $femaleCount = 1;
        }

        $specialCategories = [
            '4Ps' => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%4Ps%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%4Ps%'))->count(),
            'OSY' => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%OSY%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%OSY%'))->count(),
            'IP'  => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%IP%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%IP%'))->count(),
            'PWD' => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%PWD%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%PWD%'))->count(),
            'SP'  => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%SP%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%SP%'))->count(),
        ];

        // ── 5. Monthly Request Trends (Last 6 Months) ──
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthKey = $monthDate->format('M Y');
            $start = $monthDate->copy()->startOfMonth();
            $end = $monthDate->copy()->endOfMonth();

            $count = ServiceRequest::whereBetween('created_at', [$start, $end])->count()
                + GuidanceAppointment::whereNull('service_request_id')->whereBetween('created_at', [$start, $end])->count()
                + AdmissionApplicant::whereBetween('created_at', [$start, $end])->count();

            $monthlyTrends[$monthKey] = $count;
        }

        $totalRequests = ServiceRequest::count() + GuidanceAppointment::whereNull('service_request_id')->count() + $totalAdmissionApplicants;
        $activeRequests = ServiceRequest::whereIn('status', ServiceRequest::ACTIVE_STATUSES)->count()
            + GuidanceAppointment::whereIn('status', ['Pending Payment', 'Receipt Uploaded', 'Approved', 'In-Progress'])->count()
            + $pendingAdmissionCount;

        return [
            'guidanceOnly'              => false,
            'totalRequests'             => $totalRequests,
            'completedTests'            => $completedTests,
            'activeRequests'            => $activeRequests,
            'totalAdmissionApplicants'  => $totalAdmissionApplicants,
            'qualifiedAdmissionCount'   => $qualifiedAdmissionCount,
            'notQualifiedAdmissionCount'=> $notQualifiedAdmissionCount,
            'pendingAdmissionCount'     => $pendingAdmissionCount,
            'admissionStatusBreakdown'  => $admissionStatusBreakdown,
            'admissionByProgram'        => $admissionByProgram,
            'psychologicalRequestsCount'=> $psychologicalRequestsCount,
            'personalityRequestsCount'  => $personalityRequestsCount,
            'careerRequestsCount'       => $careerRequestsCount,
            'testingServicesTotal'      => $testingServicesTotal,
            'goodMoralTotal'            => $goodMoralTotal,
            'goodMoralClaimed'          => $goodMoralClaimed,
            'goodMoralPending'          => $goodMoralPending,
            'exitFormTotal'             => $exitFormTotal,
            'exitFormClaimed'           => $exitFormClaimed,
            'exitFormPending'           => $exitFormPending,
            'goodMoralCount'            => $goodMoralTotal,
            'exitFormCount'             => $exitFormTotal,
            'redFlagsCount'             => $redFlagsCount,
            'severityDistribution'      => $severityDistribution,
            'scaleDistributions'        => $scaleDistributions,
            'personalityTraits'         => $personalityTraits,
            'programTestingVolume'      => $programTestingVolume,
            'officialPrograms'          => $officialPrograms,
            'maleCount'                 => $maleCount,
            'femaleCount'               => $femaleCount,
            'specialCategories'         => $specialCategories,
            'monthlyTrends'             => $monthlyTrends,
            'redFlags'                  => array_slice($redFlags, 0, 25),
            'role'                      => auth()->user()?->role ?? 'admin',
        ];
    }
}
