<?php

namespace App\Services;

use App\Events\InterventionStatusUpdated;
use App\Events\ProctorEmergencyOverrideToggled;
use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceSetting;
use App\Models\GuidanceTestBatch;
use App\Models\GuidanceTestResponse;
use App\Models\GuidanceTestSecurityLog;
use App\Models\GuidanceSecurityIncident;
use App\Models\ServiceRequest;
use App\Support\CourseCatalog;
use App\Support\RequestFees;
use Illuminate\Support\Facades\DB;

/**
 * AnalyticsDashboardService
 *
 * Centralised data layer for the Executive Analytics Dashboards (/admin/analytics and /staff/analytics).
 * Computes:
 * - 1. High-Priority Action Center (pending receipts, unassigned batches, unprocessed doc requests)
 * - 2. High-Risk / Red-Flag Intervention Panel (querying guidance_test_responses JSON score summaries, student actions)
 * - 3. Real-Time Security & Proctoring Incident Feed (total infractions, max-strike lockout feed, warn/pause/force terminate)
 * - 4. Demographic & Institutional Target Analytics (gender, 4Ps/OSY/IP/PWD/SP, 11-program participation heatmap)
 * - 5. Document Fulfillment & Fee Analytics (issuance vs claimed, revenue collection summary mapped against ORs)
 * - 6. Dynamic System Controls (active cycle selector, emergency proctor pause override)
 */
class AnalyticsDashboardService
{
    // ─────────────────────────────────────────────────────────────────────────
    // 1. ACTION CENTER
    // ─────────────────────────────────────────────────────────────────────────

    public function actionCenterCounts(): array
    {
        $pendingReceipts = GuidanceAppointment::where('status', 'Receipt Uploaded')->count()
            + ServiceRequest::where('status', 'proof_review')->count();

        $pendingBatches = GuidanceTestBatch::whereNull('started_at')
            ->whereNull('archived_at')
            ->where(fn ($q) => $q->where('status', '!=', 'Completed')->orWhereNull('status'))
            ->count();

        $unprocessedDocs = ServiceRequest::whereIn('service', ['good-moral', 'exit-form'])
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->count();

        return [
            'pendingReceipts'  => $pendingReceipts,
            'pendingBatches'   => $pendingBatches,
            'unprocessedDocs'  => $unprocessedDocs,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. RED-FLAG INTERVENTION PANEL
    // ─────────────────────────────────────────────────────────────────────────

    public function redFlagIntervention(int $limit = 50): array
    {
        $responses = GuidanceTestResponse::with([
                'appointment.serviceRequest',
                'appointment.applicant',
                'applicant',
            ])
            ->latest('guidance_test_response_id')
            ->get();

        $flags = [];

        foreach ($responses as $response) {
            $summaries    = $response->testSummaries();
            $appointment  = $response->appointment;
            $applicant    = $response->applicant ?? $appointment?->applicant;
            $sr           = $appointment?->serviceRequest;

            $studentName  = mb_strtoupper(
                $applicant?->full_name
                ?? ($sr ? trim($sr->first_name . ' ' . $sr->last_name) : 'Anonymous Examinee')
            );
            $studentId    = $appointment?->student_number
                ?: ($applicant?->application_number ?: ($sr?->student_number ?: 'Not Provided'));
            $course       = $appointment ? $appointment->courseLabel()
                : ($sr?->courseLabel() ?? 'Unspecified');
            $date         = $response->created_at
                ? $response->created_at->timezone('Asia/Manila')->format('M d, Y h:i A')
                : 'N/A';

            $counselingStatus = $appointment?->counseling_status ?? 'Pending Review';
            $counselingNotes  = $appointment?->counseling_notes ?? '';
            $role             = auth()->user()?->role ?? 'admin';
            $viewUrl          = $appointment
                ? route($role . '.guidance-appointments.show-results', $appointment)
                : '#';
            $apptId           = $appointment?->getKey();

            // DASS-21
            if (isset($summaries['dass21']['interpretation'])) {
                foreach (['depression', 'anxiety', 'stress'] as $dim) {
                    $sev = $summaries['dass21']['interpretation'][$dim] ?? null;
                    if (in_array($sev, ['Severe', 'Extremely Severe'], true)) {
                        $flags[] = $this->buildFlag(
                            $studentName, $studentId, $course,
                            'DASS-21 ' . ucfirst($dim), $sev, $date,
                            $viewUrl, $counselingStatus, $counselingNotes, $apptId,
                            $response->guidance_test_response_id
                        );
                    }
                }
            }

            // PHQ-9
            $phqSev = $summaries['phq9']['interpretation']['severity'] ?? null;
            if (in_array($phqSev, ['Moderately Severe', 'Severe'], true)) {
                $flags[] = $this->buildFlag(
                    $studentName, $studentId, $course,
                    'PHQ-9 Mood Assessment', $phqSev, $date,
                    $viewUrl, $counselingStatus, $counselingNotes, $apptId,
                    $response->guidance_test_response_id
                );
            }

            // GAD-7
            $gadSev = $summaries['gad7']['interpretation']['severity'] ?? null;
            if ($gadSev === 'Severe') {
                $flags[] = $this->buildFlag(
                    $studentName, $studentId, $course,
                    'GAD-7 Generalized Anxiety', $gadSev, $date,
                    $viewUrl, $counselingStatus, $counselingNotes, $apptId,
                    $response->guidance_test_response_id
                );
            }
        }

        $unique = [];
        foreach ($flags as $flag) {
            $key = $flag['response_id'] . '|' . $flag['scale'];
            $unique[$key] = $flag;
        }

        return array_values(array_slice($unique, 0, $limit));
    }

    private function buildFlag(
        string $name, string $studentId, string $course,
        string $scale, string $severity, string $date,
        string $viewUrl, string $counselingStatus, ?string $counselingNotes,
        ?int $appointmentId, int $responseId
    ): array {
        return [
            'name'              => $name,
            'student_id'        => $studentId,
            'course'            => $course,
            'scale'             => $scale,
            'severity'          => $severity,
            'date'              => $date,
            'view_url'          => $viewUrl,
            'counseling_status' => $counselingStatus,
            'counseling_notes'  => $counselingNotes,
            'appointment_id'    => $appointmentId,
            'response_id'       => $responseId,
        ];
    }

    /**
     * Retrieve full confidential profile data for the secure modal.
     */
    public function getConfidentialProfile(int $responseId): ?array
    {
        $response = GuidanceTestResponse::with([
            'appointment.applicant',
            'appointment.serviceRequest',
            'applicant',
        ])->find($responseId);

        if (!$response) {
            return null;
        }

        $appointment = $response->appointment;
        $applicant   = $response->applicant ?? $appointment?->applicant;
        $sr          = $appointment?->serviceRequest;

        $studentName = mb_strtoupper(
            $applicant?->full_name
            ?? ($sr ? trim($sr->first_name . ' ' . $sr->last_name) : 'Anonymous Examinee')
        );
        $studentId   = $appointment?->student_number
            ?: ($applicant?->application_number ?: ($sr?->student_number ?: 'Not Provided'));
        $course      = $appointment ? $appointment->courseLabel() : ($sr?->courseLabel() ?? 'Unspecified');

        return [
            'response_id'       => $response->guidance_test_response_id,
            'appointment_id'    => $appointment?->getKey(),
            'student_name'      => $studentName,
            'student_id'        => $studentId,
            'course'            => $course,
            'email'             => $applicant?->email ?? $sr?->email ?? 'N/A',
            'contact'           => $applicant?->contact_number ?? $sr?->contact_number ?? 'N/A',
            'date_taken'        => $response->created_at ? $response->created_at->timezone('Asia/Manila')->format('F d, Y h:i A') : 'N/A',
            'counseling_status' => $appointment?->counseling_status ?? 'Pending Review',
            'counseling_notes'  => $appointment?->counseling_notes ?? '',
            'score_summary'     => $response->testSummaries(),
            'answers'           => $response->answers ?? [],
        ];
    }

    /**
     * Update counseling / intervention status for an appointment.
     */
    public function updateIntervention(GuidanceAppointment $appointment, string $status, ?string $notes = null): GuidanceAppointment
    {
        $appointment->counseling_status = $status;
        if ($notes !== null) {
            $appointment->counseling_notes = $notes;
        }
        $appointment->counseling_updated_at = now();
        $appointment->save();

        // Dispatch signal broadcast
        event(new InterventionStatusUpdated(
            $appointment->getKey(),
            $status,
            $appointment->counseling_notes,
            $appointment->counseling_updated_at->toIso8601String()
        ));

        return $appointment;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. REAL-TIME SECURITY & PROCTORING FEED
    // ─────────────────────────────────────────────────────────────────────────

    public function proctoringFeed(): array
    {
        $threshold = (int) GuidanceSetting::valueOf('strike_threshold', '3');

        $totalIncidents = GuidanceSecurityIncident::count() + GuidanceTestSecurityLog::count();

        // Active sessions currently at maximum strikes or locked out
        $lockedAppts = GuidanceAppointment::where(function ($q) use ($threshold) {
                $q->where('strike_count', '>=', $threshold)
                  ->orWhereNotNull('terminated_at');
            })
            ->whereIn('status', ['In-Progress', 'Completed'])
            ->with(['applicant', 'serviceRequest'])
            ->latest('guidance_appointment_id')
            ->limit(30)
            ->get()
            ->map(function ($appt) use ($threshold) {
                $name = $appt->applicant?->full_name
                    ?? trim(($appt->serviceRequest?->first_name ?? '') . ' ' . ($appt->serviceRequest?->last_name ?? ''));
                return [
                    'appointment_id'     => $appt->getKey(),
                    'student_name'       => mb_strtoupper($name) ?: 'Unknown Examinee',
                    'student_id'         => $appt->student_number ?? $appt->serviceRequest?->student_number ?? 'N/A',
                    'strike_count'       => $appt->strike_count,
                    'threshold'          => $threshold,
                    'status'             => $appt->status,
                    'terminated'         => (bool) $appt->terminated_at,
                    'termination_reason' => $appt->termination_reason,
                    'started_at'         => $appt->started_at?->timezone('Asia/Manila')->format('h:i A') ?? 'N/A',
                ];
            })
            ->toArray();

        $recentIncidents = GuidanceSecurityIncident::with(['appointment.applicant', 'appointment.serviceRequest'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn ($inc) => [
                'id'            => $inc->id,
                'student_name'  => mb_strtoupper($inc->appointment?->applicant?->full_name
                    ?? trim(($inc->appointment?->serviceRequest?->first_name ?? '') . ' ' . ($inc->appointment?->serviceRequest?->last_name ?? ''))
                    ?: 'Unknown'),
                'type'          => GuidanceSecurityIncident::TYPES[$inc->incident_type] ?? $inc->incident_type,
                'strike'        => $inc->strike_number,
                'time'          => $inc->created_at ? $inc->created_at->timezone('Asia/Manila')->diffForHumans() : 'N/A',
            ])
            ->toArray();

        $proctoringPaused = (bool) GuidanceSetting::valueOf('proctor_pause_active', '0');

        return [
            'totalIncidents'   => $totalIncidents,
            'lockedAppts'      => $lockedAppts,
            'recentIncidents'  => $recentIncidents,
            'threshold'        => $threshold,
            'proctoringPaused' => $proctoringPaused,
        ];
    }

    /**
     * Execute staff proctor action on a live session (warn, pause, force_terminate).
     */
    public function executeProctorAction(GuidanceAppointment $appointment, string $action, ?string $reason = null): array
    {
        $sessionService = app(GuidanceAssessmentSessionService::class);

        if ($action === 'force_terminate') {
            $terminationReason = $reason ?: 'Exam forcibly terminated by staff proctor for security non-compliance.';
            $sessionService->terminateForViolation($appointment, $terminationReason);
            return [
                'success' => true,
                'action'  => 'force_terminate',
                'message' => 'Assessment has been forcibly terminated.',
                'status'  => $appointment->fresh()->status,
            ];
        }

        if ($action === 'warn') {
            // Log security incident / proctor warning
            $appointment->securityIncidents()->create([
                'event_id'      => (string) \Illuminate\Support\Str::uuid(),
                'incident_type' => 'proctor_warning',
                'strike_number' => $appointment->strike_count,
                'created_at'    => now(),
            ]);

            return [
                'success' => true,
                'action'  => 'warn',
                'message' => 'Proctor warning transmitted to examinee console.',
                'status'  => $appointment->status,
            ];
        }

        if ($action === 'pause') {
            // Toggle pause or add 10 minutes extension
            if ($appointment->expires_at) {
                $appointment->expires_at = $appointment->expires_at->addMinutes(10);
                $appointment->save();
            }

            return [
                'success' => true,
                'action'  => 'pause',
                'message' => 'Session timer extended by 10 minutes (proctor pause applied).',
                'status'  => $appointment->status,
            ];
        }

        return ['success' => false, 'message' => 'Unknown action'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. DEMOGRAPHIC & INSTITUTIONAL TARGET ANALYTICS
    // ─────────────────────────────────────────────────────────────────────────

    public function demographicAnalytics(): array
    {
        $maleCount   = ServiceRequest::whereRaw('LOWER(gender) = ?', ['male'])->count()
            + AdmissionApplicant::whereRaw('LOWER(sex) = ?', ['male'])->count();
        $femaleCount = ServiceRequest::whereRaw('LOWER(gender) = ?', ['female'])->count()
            + AdmissionApplicant::whereRaw('LOWER(sex) = ?', ['female'])->count();

        if ($maleCount === 0 && $femaleCount === 0) {
            $maleCount = $femaleCount = 1;
        }

        $specialCategories = [
            '4Ps' => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%4Ps%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%4Ps%'))->count(),
            'OSY' => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%OSY%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%OSY%'))->count(),
            'IP'  => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%IP%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%IP%'))->count(),
            'PWD' => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%PWD%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%PWD%'))->count(),
            'SP'  => AdmissionApplicant::where(fn ($q) => $q->where('special_group', 'like', '%SP%')->orWhere('4ps_osy_ip_pwd_sp', 'like', '%SP%'))->count(),
        ];

        // 11 Campus degree programs registered in courses table
        $officialPrograms      = CourseCatalog::allOptions();
        $programParticipation  = array_fill_keys(array_keys($officialPrograms), 0);

        $srCounts = ServiceRequest::selectRaw('course, COUNT(*) as total')
            ->whereNotNull('course')
            ->groupBy('course')
            ->pluck('total', 'course')
            ->toArray();

        foreach ($srCounts as $code => $cnt) {
            $norm = CourseCatalog::normalizeLegacy($code) ?? $code;
            if (isset($programParticipation[$norm])) {
                $programParticipation[$norm] += $cnt;
            }
        }

        $gaCounts = GuidanceAppointment::whereNull('service_request_id')
            ->selectRaw('origin_course, COUNT(*) as total')
            ->whereNotNull('origin_course')
            ->groupBy('origin_course')
            ->pluck('total', 'origin_course')
            ->toArray();

        foreach ($gaCounts as $code => $cnt) {
            $norm = CourseCatalog::normalizeLegacy($code) ?? $code;
            if (isset($programParticipation[$norm])) {
                $programParticipation[$norm] += $cnt;
            }
        }

        return [
            'maleCount'            => $maleCount,
            'femaleCount'          => $femaleCount,
            'specialCategories'    => $specialCategories,
            'programParticipation' => $programParticipation,
            'officialPrograms'     => $officialPrograms,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. DOCUMENT FULFILLMENT & FEE ANALYTICS
    // ─────────────────────────────────────────────────────────────────────────

    public function documentFeeAnalytics(): array
    {
        // Good Moral
        $gmTotal   = ServiceRequest::where('service', 'good-moral')->count();
        $gmIssued  = ServiceRequest::where('service', 'good-moral')->where('status', 'ready')->count();
        $gmClaimed = ServiceRequest::where('service', 'good-moral')->whereIn('status', ['completed', 'claimed'])->count();
        $gmPending = ServiceRequest::where('service', 'good-moral')->whereIn('status', ['pending', 'approved', 'processing', 'proof_review'])->count();

        // Exit Form
        $efTotal   = ServiceRequest::where('service', 'exit-form')->count();
        $efIssued  = ServiceRequest::where('service', 'exit-form')->where('status', 'ready')->count();
        $efClaimed = ServiceRequest::where('service', 'exit-form')->whereIn('status', ['completed', 'claimed'])->count();
        $efPending = ServiceRequest::where('service', 'exit-form')->whereIn('status', ['pending', 'approved', 'processing', 'proof_review'])->count();

        $feeSchedule = [
            'Good Moral Certificate'   => RequestFees::PESOS['good-moral'] ?? 60,
            'Psychological Assessment' => RequestFees::PESOS['psychological'] ?? 60,
            'Personality Assessment'   => RequestFees::PESOS['personality'] ?? 60,
            'Career Profiling'         => RequestFees::PESOS['career'] ?? 60,
            'Transcript of Records'    => 230,
            'Exit Form Clearance'      => 0,
        ];

        // Revenue mapped against official receipt (or_number) entries
        $gmRevenue = ServiceRequest::where('service', 'good-moral')
            ->whereNotNull('or_number')
            ->where('or_number', '!=', '')
            ->count() * 60;

        $psychRevenue = (GuidanceAppointment::where('test_category', 'psychological')
            ->whereNotNull('or_number')->where('or_number', '!=', '')->count()
            + ServiceRequest::where('service', 'psychological')
            ->whereNotNull('or_number')->where('or_number', '!=', '')->count()) * 60;

        $persRevenue = (GuidanceAppointment::where('test_category', 'personality')
            ->whereNotNull('or_number')->where('or_number', '!=', '')->count()
            + ServiceRequest::where('service', 'personality')
            ->whereNotNull('or_number')->where('or_number', '!=', '')->count()) * 60;

        $careerRevenue = (GuidanceAppointment::where('test_category', 'career')
            ->whereNotNull('or_number')->where('or_number', '!=', '')->count()
            + ServiceRequest::where('service', 'career')
            ->whereNotNull('or_number')->where('or_number', '!=', '')->count()) * 60;

        $generalTestingRevenue = ServiceRequest::where('service', 'testing')
            ->whereNotNull('or_number')->where('or_number', '!=', '')->count() * 60;

        $totalRevenue = $gmRevenue + $psychRevenue + $persRevenue + $careerRevenue + $generalTestingRevenue;

        $revenueBreakdown = [
            'Good Moral (₱60)'           => $gmRevenue,
            'Psychological Assessment (₱60)' => $psychRevenue,
            'Personality Test (₱60)'     => $persRevenue,
            'Career Profiling (₱60)'     => $careerRevenue,
            'General Testing Services'   => $generalTestingRevenue,
        ];

        return [
            'gmTotal'          => $gmTotal,
            'gmIssued'         => $gmIssued,
            'gmClaimed'        => $gmClaimed,
            'gmPending'        => $gmPending,
            'efTotal'          => $efTotal,
            'efIssued'         => $efIssued,
            'efClaimed'        => $efClaimed,
            'efPending'        => $efPending,
            'totalRevenue'     => $totalRevenue,
            'revenueBreakdown' => $revenueBreakdown,
            'feeSchedule'      => $feeSchedule,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. DYNAMIC SYSTEM QUICK CONTROLS
    // ─────────────────────────────────────────────────────────────────────────

    public function systemControls(): array
    {
        $cycles = AdmissionCycle::orderByDesc('id')
            ->get(['id', 'cycle_name', 'name', 'status', 'is_active'])
            ->map(fn ($c) => [
                'id'        => $c->id,
                'label'     => $c->display_name,
                'status'    => $c->status,
                'is_active' => (bool) $c->is_active,
            ])
            ->toArray();

        $activeCycleId    = AdmissionCycle::active()?->id;
        $proctoringPaused = (bool) GuidanceSetting::valueOf('proctor_pause_active', '0');

        return [
            'admissionCycles'  => $cycles,
            'activeCycleId'    => $activeCycleId,
            'proctoringPaused' => $proctoringPaused,
        ];
    }

    /**
     * Toggle campus-wide emergency proctor override.
     */
    public function toggleProctorOverride(): bool
    {
        $current = (bool) GuidanceSetting::valueOf('proctor_pause_active', '0');
        $newVal  = !$current;

        GuidanceSetting::updateOrCreate(
            ['key' => 'proctor_pause_active'],
            ['value' => $newVal ? '1' : '0', 'group' => 'guidance_security']
        );

        event(new ProctorEmergencyOverrideToggled(
            $newVal,
            auth()->user()->name ?? 'System Staff',
            now()->toIso8601String()
        ));

        return $newVal;
    }
}
