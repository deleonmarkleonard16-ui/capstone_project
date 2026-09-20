<?php

namespace App\Http\Controllers;

use App\Models\GuidanceTestBatch;
use App\Models\GuidanceAppointment;
use App\Models\ServiceRequest;
use App\Services\GuidanceCategories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PsychologicalRequestController extends Controller
{
    public const STATUSES = ['pending', 'approved', 'proof_review', 'processing', 'ready', 'scheduled', 'completed', 'declined', 'cancelled', 'void'];

    public const MODULES = [
        'psychological' => [
            'key' => 'psychological',
            'label' => 'Psychological Assessment',
            'short_label' => 'Psychological',
            'batch_label' => 'Psychological Assessment',
            'tests' => ['dass21' => 'DASS-21', 'phq9' => 'PHQ-9', 'gad7' => 'GAD-7'],
            'description' => 'Review Psychological Assessment requests, receipts, and appointment schedules.',
        ],
        'personality' => [
            'key' => 'personality',
            'label' => 'Personality Test',
            'short_label' => 'Personality',
            'batch_label' => 'Personality Test',
            'tests' => ['bfpi' => 'BFPI'],
            'description' => 'Review Big Five Personality Inventory (BFPI) requests, receipts, and appointment schedules.',
        ],
        'career' => [
            'key' => 'career',
            'label' => 'Career Test',
            'short_label' => 'Career',
            'batch_label' => 'Career Test',
            'tests' => ['career' => 'Career RIASEC'],
            'description' => 'Review RIASEC Career Interest Assessment requests, receipts, and appointment schedules.',
        ],
    ];

    public function index(Request $request)
    {
        // Automatically check and void expired requests
        ServiceRequest::expirePendingRequests();

        app(\App\Services\GuidanceAssessmentSessionService::class)->expireDue();

        $moduleKey = $request->route('module', 'psychological');
        if (!array_key_exists($moduleKey, self::MODULES)) {
            $moduleKey = 'psychological';
        }
        $module = self::MODULES[$moduleKey];

        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(array_merge(self::STATUSES, \App\Models\GuidanceAppointment::STATUSES))],
            'test_type' => ['nullable', Rule::in(array_keys($module['tests']))],
            'course' => ['nullable', Rule::in(array_keys(\App\Support\CourseCatalog::allOptions()))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $base = ServiceRequest::where('service', 'testing')->whereJsonContains('tests', $moduleKey);
        $counts = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $stats = [
            'Total requests' => (clone $base)->count(),
            'Pending review' => $counts['pending'] ?? 0,
            'Proof review' => $counts['proof_review'] ?? 0,
            'Approved / Active' => $counts['approved'] ?? 0,
            'Scheduled' => $counts['scheduled'] ?? 0,
            'Completed' => $counts['completed'] ?? 0,
            'Void / Expired' => $counts['void'] ?? 0,
            'Archived' => (clone $base)->whereNotNull('archived_at')->count(),
        ];
        $mode = $request->route('mode', 'queue');
        $query = clone $base;

        if ($mode === 'archive') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        if (! empty($filters['q'])) {
            foreach (preg_split('/\s+/', trim($filters['q'])) as $q) {
                $query->where(function ($sub) use ($q) {
                $sub->where('reference', 'like', "%$q%")
                    ->orWhere('first_name', 'like', "%$q%")
                    ->orWhere('last_name', 'like', "%$q%")
                    ->orWhere('student_number', 'like', "%$q%")
                    ->orWhereHas('guidanceAppointments', fn ($appointments) => $appointments->where('request_code', 'like', "%$q%")->orWhere('origin_section', 'like', "%$q%"));
                });
            }
        }

        if (! empty($filters['status'])) {
            if (in_array($filters['status'], \App\Models\GuidanceAppointment::STATUSES, true)) {
                $query->whereHas('guidanceAppointments', fn ($appointments) => $appointments->where('status', $filters['status']));
            } else {
                $query->where('status', $filters['status']);
            }
        }
        if (!empty($filters['test_type'])) {
            $query->whereHas('guidanceAppointments', fn ($appointments) => $appointments->whereJsonContains('test_types', $filters['test_type'])->orWhere('test_type', $filters['test_type']));
        }
        if (!empty($filters['course'])) $query->where('course', $filters['course']);
        \App\Support\TableFilters::dates($query, $filters);

        return view('staff.psychological.index', [
            'requests' => $query->with(['guidanceAppointments.response', 'guidanceAppointments.applicant'])->latest()->paginate(15)->withQueryString(),
            'stats' => $stats,
            'counts' => $counts,
            'mode' => $mode,
            'moduleKey' => $moduleKey,
            'module' => $module,
            'genderCounts' => (clone $base)->where('status', 'completed')->selectRaw('gender, COUNT(*) as total')->groupBy('gender')->pluck('total', 'gender'),
            'archivedAppointments' => $mode === 'archive' ? \App\Support\TableFilters::dates(GuidanceAppointment::with('applicant')->whereNull('batch_id')->where('test_category', $moduleKey)->where('is_archived', true)->when($filters['course'] ?? null, fn ($q, $course) => $q->where(fn ($a) => $a->where('origin_course', $course)->orWhereHas('serviceRequest', fn ($r) => $r->where('course', $course))))->when($filters['q'] ?? null, function ($q, $search) { foreach (\App\Support\TableFilters::words($search) as $word) { $like = '%'.$word.'%'; $q->where(fn ($a) => $a->where('request_code', 'like', $like)->orWhere('student_id_number', 'like', $like)->orWhere('origin_section', 'like', $like)->orWhereHas('applicant', fn ($s) => $s->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))); } }), $filters)->latest('archived_at')->paginate(10, ['*'], 'appointments_page')->withQueryString() : null,
            'archivedBatches' => $mode === 'archive' ? \App\Support\TableFilters::dates(GuidanceTestBatch::where('test_type', $module['batch_label'])->where('status', 'Completed')->when($filters['course'] ?? null, fn ($q, $course) => $q->where('course', $course))->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($b) => $b->where('batch_name', 'like', '%'.$search.'%')->orWhere('year_section', 'like', '%'.$search.'%')->orWhereHas('appointments', fn ($a) => $a->where('student_id_number', 'like', '%'.$search.'%')->orWhere('request_code', 'like', '%'.$search.'%')->orWhereHas('applicant', fn ($s) => $s->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')))->orWhereHas('makeupAppointments', fn ($a) => $a->where('student_id_number', 'like', '%'.$search.'%')->orWhere('request_code', 'like', '%'.$search.'%')->orWhereHas('applicant', fn ($s) => $s->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'))))), $filters)->latest('batch_id')->paginate(10, ['*'], 'batches_page')->withQueryString() : null,
        ]);
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($serviceRequest->service === 'testing', 404);
        if ($serviceRequest->guidanceAppointments()->exists()) {
            throw ValidationException::withMessages(['status' => 'Use Guidance Testing to verify the receipt and issue assessment passes.']);
        }
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'staff_message' => 'nullable|string|max:2000',
            'scheduled_at' => 'nullable|date',
            'venue' => 'nullable|string|max:255',
        ]);

        if (in_array($data['status'], ['proof_review', 'processing', 'ready', 'scheduled', 'completed'], true) && ! $serviceRequest->proof_path) {
            throw ValidationException::withMessages(['status' => 'A paid/stamped stub or receipt must be uploaded before processing this request.']);
        }

        if ($data['status'] === 'scheduled') {
            if (! in_array($serviceRequest->status, ['approved', 'proof_review', 'scheduled'], true)) {
                throw ValidationException::withMessages(['status' => 'A request can only be scheduled after it has been approved or submitted for review.']);
            }
        }

        if ($data['status'] === 'completed') {
            if ($serviceRequest->status === 'scheduled' && $serviceRequest->scheduled_at && now()->lessThan($serviceRequest->scheduled_at)) {
                throw ValidationException::withMessages(['status' => 'An appointment cannot be marked completed before the scheduled time.']);
            }
            if (! in_array($serviceRequest->status, ['scheduled', 'processing', 'ready', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => 'Request is not in a valid state to be completed.']);
            }
        }

        DB::transaction(function () use ($serviceRequest, $data) {
            $entry = ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
            $update = [
                'status' => $data['status'],
                'staff_message' => $data['staff_message'] ?? null,
            ];
            if (isset($data['scheduled_at'])) {
                $update['scheduled_at'] = \Illuminate\Support\Carbon::parse($data['scheduled_at'], 'Asia/Manila')->setTimezone('UTC');
            }
            if (isset($data['venue'])) {
                $update['venue'] = $data['venue'];
            }
            if (in_array($data['status'], ['completed', 'declined', 'cancelled', 'void'], true)) {
                $update['archived_at'] = now();
            }
            $entry->update($update);
        });

        $moduleKey = $request->route('module', 'psychological');
        if (! array_key_exists($moduleKey, self::MODULES)) {
            $moduleKey = 'psychological';
        }
        $fallback = route(auth()->user()->role.'.'.$moduleKey.'.index');
        $previous = url()->previous();
        $redirectUrl = (! empty($previous) && ! str_contains($previous, '/notifications')) ? $previous : $fallback;

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Request updated successfully.',
                'redirect' => $redirectUrl,
            ]);
        }

        return redirect()->to($redirectUrl)->with('success', 'Request updated successfully.');
    }

    public function upload(Request $request)
    {
        $request->merge(['reference' => is_string($request->input('reference')) ? strtoupper(trim($request->input('reference'))) : $request->input('reference')]);
        $data = $request->validate(['reference' => ServiceRequest::referenceRules(),
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $isPrefixRef = (bool) preg_match('/^(?:G|TR|GM|EF)-/i', $data['reference']);
        $data['reference'] = $isPrefixRef ? strtoupper($data['reference']) : strtolower($data['reference']);
        DB::transaction(function () use ($request, $data) {
            $entry = ServiceRequest::where('reference', $data['reference'])->lockForUpdate()->first();
            abort_unless($entry, 404);
            if ($entry->batch_id) {
                throw ValidationException::withMessages(['proof' => 'Use the batch QR link to verify your roster identity before uploading a receipt.']);
            }
            if ($entry->guidanceAppointments()->exists()) {
                throw ValidationException::withMessages(['proof' => 'Use the receipt upload form in Track Existing Request for this guidance appointment.']);
            }
            if (! in_array($entry->status, ['pending', 'approved'], true)) throw ValidationException::withMessages(['proof' => 'A stub can only be uploaded while awaiting payment or a replacement stub.']);
            $path = $request->file('proof')->store('receipts', 'local');
            abort_unless($path, 500, 'Unable to save receipt. Please try again.');
            $entry->update(['proof_path' => $path, 'status' => 'proof_review']);
        });
        $request->session()->put('portal_request_reference', $data['reference']);
        return redirect()->route('portal.index')->with('portal_notice', 'Paid stub or receipt uploaded. The guidance office will verify it and process your request.');
    }

    public function proof(ServiceRequest $serviceRequest)
    {
        abort_unless($serviceRequest->proof_path, 404);
        abort_unless(Storage::disk('local')->exists($serviceRequest->proof_path), 404);
        return response()->file(Storage::disk('local')->path($serviceRequest->proof_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
