<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\Applicant;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceSetting;
use App\Models\GuidanceTestResponse;
use App\Models\ServiceRequest;
use App\Services\GuidanceAnalyticsService;
use App\Services\GuidanceAssessmentSessionService;
use App\Support\CourseCatalog;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
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
    public function dashboard(Request $request)
    {
        app(GuidanceAssessmentSessionService::class)->expireDue();

        $metrics = $this->compileExecutiveMetrics();

        return view('admin.analytics', $metrics);
    }

    /**
     * Export Executive Analytics Summary as PDF.
     */
    public function exportPdf(Request $request)
    {
        $data = $this->compileExecutiveMetrics();
        $data['counselorName'] = GuidanceSetting::valueOf('guidance_counselor_name', 'Ms. Noemi C. Carlos');
        $data['academicYear'] = GuidanceSetting::valueOf('academic_year', date('Y') . '-' . (date('Y') + 1));
        $data['generatedAt'] = now()->timezone('Asia/Manila')->format('F d, Y h:i A');

        $html = view('admin.analytics-pdf', $data)->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'PSU_San_Carlos_Institutional_Analytics_' . now()->format('Y_m_d') . '.pdf';

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /**
     * Export Executive Analytics Data as Excel-compatible CSV.
     */
    public function exportExcel(Request $request)
    {
        $filename = 'PSU_San_Carlos_Analytics_Data_' . now()->format('Y_m_d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () {
            $output = fopen('php://output', 'w');
            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, [
                'Reference Code',
                'Student / Applicant Name',
                'Student ID Number',
                'Gender',
                'Degree Program',
                'Service / Assessment',
                'O.R. Number',
                'O.R. Date',
                'Status',
                'Severity / Interpretations',
                'Date Recorded',
            ]);

            // Guidance Appointments & Completed Tests
            $appointments = GuidanceAppointment::with(['applicant', 'serviceRequest', 'response'])
                ->latest('guidance_appointment_id')
                ->cursor();

            foreach ($appointments as $app) {
                $name = $app->applicant?->full_name ?? ($app->serviceRequest ? trim($app->serviceRequest->first_name . ' ' . $app->serviceRequest->last_name) : 'N/A');
                $sid = $app->student_number ?? ($app->applicant?->application_number ?? 'Not Provided');
                $gender = $app->applicant?->gender ?? ($app->serviceRequest?->gender ?? 'Not Specified');
                $course = CourseCatalog::allOptions()[$app->courseLabel()] ?? $app->courseLabel();
                $tests = $app->testLabel();
                $orNumber = $app->or_number ?: ($app->serviceRequest?->or_number ?? 'N/A');
                $orDate = $app->or_date ? $app->or_date->format('Y-m-d') : ($app->serviceRequest?->or_date ? Carbon::parse($app->serviceRequest->or_date)->format('Y-m-d') : 'N/A');

                $interpretations = [];
                if ($app->response) {
                    $summaries = $app->response->testSummaries();
                    foreach ($summaries as $testKey => $sum) {
                        $interp = $sum['interpretation'] ?? [];
                        foreach ($interp as $dim => $val) {
                            $interpretations[] = ucfirst($dim) . ': ' . $val;
                        }
                    }
                }
                $interpStr = !empty($interpretations) ? implode('; ', $interpretations) : 'None';

                fputcsv($output, [
                    $app->request_code,
                    mb_strtoupper($name),
                    $sid,
                    ucfirst($gender),
                    $course,
                    $tests,
                    $orNumber,
                    $orDate,
                    $app->status,
                    $interpStr,
                    $app->created_at ? $app->created_at->format('Y-m-d H:i') : '',
                ]);
            }

            // General Document Requests (Good Moral, Exit Form)
            $requests = ServiceRequest::whereIn('service', ['good-moral', 'exit-form'])
                ->latest('id')
                ->cursor();

            foreach ($requests as $req) {
                $name = trim($req->first_name . ' ' . $req->last_name);
                $course = CourseCatalog::allOptions()[$req->course] ?? $req->course;

                fputcsv($output, [
                    $req->reference,
                    mb_strtoupper($name),
                    $req->student_number ?: 'Not Provided',
                    ucfirst($req->gender ?? 'Not Specified'),
                    $course,
                    ServiceRequest::SERVICES[$req->service] ?? $req->service,
                    $req->or_number ?: 'N/A',
                    $req->or_date ? Carbon::parse($req->or_date)->format('Y-m-d') : 'N/A',
                    ucfirst($req->status),
                    'Document Request (' . ($req->copies ?? 1) . ' copies)',
                    $req->created_at ? $req->created_at->format('Y-m-d H:i') : '',
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Compile comprehensive institutional analytics metrics.
     */
    private function compileExecutiveMetrics(): array
    {
        $guidanceOnly = auth()->user()?->role === 'staff';
        // ── 1. Executive Summary Cards ──
        $serviceRequestsCount = ServiceRequest::count();
        $standaloneAppointmentsCount = GuidanceAppointment::whereNull('service_request_id')->count();
        $totalRequests = $serviceRequestsCount + $standaloneAppointmentsCount;

        $completedTests = GuidanceAppointment::where('status', 'Completed')->count();
        $activeRequests = ServiceRequest::whereIn('status', ServiceRequest::ACTIVE_STATUSES)->count()
            + GuidanceAppointment::whereIn('status', ['Pending Payment', 'Receipt Uploaded', 'Approved', 'In-Progress'])->count();
        $goodMoralCount = ServiceRequest::where('service', 'good-moral')->count();
        $exitFormCount = ServiceRequest::where('service', 'exit-form')->count();

        // ── 2. Psychometric Severity Distributions ──
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

        // ── 3. Program Choice Distribution (10 official PSU San Carlos degree programs) ──
        $officialPrograms = CourseCatalog::OPTIONS;
        $programTestingVolume = [];
        foreach ($officialPrograms as $code => $title) {
            $programTestingVolume[$code] = 0;
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

        // ── 4. Socio-Demographic Breakdown ──
        $maleCount = ServiceRequest::whereRaw('LOWER(gender) = ?', ['male'])->count()
            + Applicant::when($guidanceOnly, fn ($q) => $q->whereIn('id', GuidanceAppointment::select('applicant_id')))->whereHas('genderLookup', fn ($q) => $q->where('slug', 'male'))->count()
            + ($guidanceOnly ? 0 : AdmissionApplicant::whereRaw('LOWER(gender) = ?', ['male'])->count());

        $femaleCount = ServiceRequest::whereRaw('LOWER(gender) = ?', ['female'])->count()
            + Applicant::when($guidanceOnly, fn ($q) => $q->whereIn('id', GuidanceAppointment::select('applicant_id')))->whereHas('genderLookup', fn ($q) => $q->where('slug', 'female'))->count()
            + ($guidanceOnly ? 0 : AdmissionApplicant::whereRaw('LOWER(gender) = ?', ['female'])->count());

        if (!$guidanceOnly && $maleCount === 0 && $femaleCount === 0) {
            $maleCount = 1; // prevent division by zero in charts
            $femaleCount = 1;
        }

        $specialCategories = $guidanceOnly ? [] : [
            '4Ps' => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%4Ps%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%4Ps%'))->count(),
            'OSY' => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%OSY%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%OSY%'))->count(),
            'IP'  => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%IP%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%IP%'))->count(),
            'PWD' => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%PWD%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%PWD%'))->count(),
            'SP'  => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%SP%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%SP%'))->count(),
        ];

        // ── 5. Monthly Request Trends (Past 6 Months) ──
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthKey = $monthDate->format('M Y');
            $start = $monthDate->copy()->startOfMonth();
            $end = $monthDate->copy()->endOfMonth();

            $count = ServiceRequest::whereBetween('created_at', [$start, $end])->count()
                + GuidanceAppointment::whereNull('service_request_id')->whereBetween('created_at', [$start, $end])->count();

            $monthlyTrends[$monthKey] = $count;
        }

        return [
            'guidanceOnly'          => $guidanceOnly,
            'totalRequests'         => $totalRequests,
            'completedTests'        => $completedTests,
            'activeRequests'        => $activeRequests,
            'goodMoralCount'        => $goodMoralCount,
            'exitFormCount'         => $exitFormCount,
            'redFlagsCount'         => $redFlagsCount,
            'severityDistribution'  => $severityDistribution,
            'scaleDistributions'    => $scaleDistributions,
            'personalityTraits'     => $personalityTraits,
            'programTestingVolume'  => $programTestingVolume,
            'officialPrograms'      => $officialPrograms,
            'maleCount'             => $maleCount,
            'femaleCount'           => $femaleCount,
            'specialCategories'     => $specialCategories,
            'monthlyTrends'         => $monthlyTrends,
            'redFlags'              => array_slice($redFlags, 0, 25), // Show top 25 recent red flags
            'role'                  => auth()->user()?->role ?? 'admin',
        ];
    }
}
