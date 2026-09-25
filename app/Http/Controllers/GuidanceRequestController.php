<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Services\GuidancePortalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class GuidanceRequestController extends Controller
{
    /**
     * Handle submission of guidance and testing service requests.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'service' => ['required', Rule::in(array_keys(ServiceRequest::SERVICES))],
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'student_status' => ['required', Rule::in(['student', 'alumni', 'Currently Enrolled', 'Alumni'])],
            'student_id' => 'required_if:student_status,student,Currently Enrolled|nullable|string|max:50',
            'student_number' => 'nullable|string|max:50',
            'year_graduated' => 'required_if:student_status,alumni,Alumni|nullable|digits:4',
            'course' => \App\Support\CourseCatalog::rule(),
            'purpose' => 'exclude_if:service,testing|required_without:reason|nullable|string|max:2000',
            'reason' => ['required_if:service,testing', 'required_without:purpose', 'nullable', Rule::in(['OJT', 'FIELD STUDY', 'Others'])],
            'other_reason' => 'exclude_unless:reason,Others|required|string|max:2000',
            'tests' => 'exclude_unless:service,testing|required|array|min:1|max:3',
            'tests.*' => ['required', 'distinct', Rule::in(['psychological', 'personality', 'career'])],
            'copies' => 'exclude_unless:service,good-moral,exit-form|required|integer|min:1|max:10',
            'consent' => 'accepted',
        ]);

        unset($data['consent']);

        $isAlumni = in_array($data['student_status'], ['alumni', 'Alumni'], true);
        $data['student_status'] = $isAlumni ? 'alumni' : 'student';

        if ($isAlumni) {
            $yearGrad = $data['year_graduated'] ?? $request->input('student_id') ?? $request->input('student_number');
            $data['student_number'] = $yearGrad ? 'GRAD-' . $yearGrad : 'ALUMNI';
            if (Schema::hasColumn('service_requests', 'year_graduated')) {
                $data['year_graduated'] = $yearGrad;
            } else {
                unset($data['year_graduated']);
            }
            unset($data['student_id']);
        } else {
            $idNum = $data['student_id'] ?? $data['student_number'] ?? $request->input('student_number') ?? '';
            $data['student_number'] = mb_strtoupper(trim((string) $idNum));
            $data['student_id'] = $data['student_number'];
            if (Schema::hasColumn('service_requests', 'year_graduated')) {
                $data['year_graduated'] = null;
            } else {
                unset($data['year_graduated']);
            }
        }

        if (! empty($data['reason'])) {
            $data['purpose'] = $data['reason'] === 'Others' ? $data['other_reason'] : $data['reason'];
        }
        unset($data['reason'], $data['other_reason']);

        // Normalise text fields to uppercase for consistent presentation.
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = mb_strtoupper(trim($data[$field]));
            }
        }

        // Check for duplicate active request if student ID is present
        if (! empty($data['student_number']) && $data['student_number'] !== 'ALUMNI') {
            $hasActive = false;
            if ($data['service'] === 'testing') {
                $categories = $data['tests'] ?? ['psychological'];
                $activeGaStatuses = ['Pending Payment', 'Receipt Uploaded', 'Approved', 'In-Progress'];

                $hasActive = \App\Models\GuidanceAppointment::where(function ($q) use ($data) {
                        $q->where('student_id_number', $data['student_number'])
                          ->orWhereHas('serviceRequest', fn ($sr) => $sr->where('student_number', $data['student_number']));
                    })
                    ->whereIn('test_category', $categories)
                    ->whereIn('status', $activeGaStatuses)
                    ->whereNull('batch_id')
                    ->exists();

                if (! $hasActive) {
                    $hasActive = ServiceRequest::where('student_number', $data['student_number'])
                        ->where('service', 'testing')
                        ->whereIn('status', ServiceRequest::ACTIVE_STATUSES)
                        ->whereNull('archived_at')
                        ->where(function ($q) use ($categories) {
                            foreach ($categories as $cat) {
                                $q->orWhereJsonContains('tests', $cat);
                            }
                        })
                        ->exists();
                }
            } else {
                $hasActive = ServiceRequest::hasActiveRequest($data['student_number'], $data['service']);
            }

            if ($hasActive) {
                $message = 'You already have an active request for this item. Please track your existing request using your Tracking Reference code.';
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                        'errors' => ['service' => [$message]],
                    ], 422);
                }

                return back()
                    ->with('portal_notice', $message)
                    ->withErrors(['service' => $message])
                    ->withInput();
            }
        }

        $entry = $data['service'] === 'testing'
            ? app(GuidancePortalService::class)->create($data)
            : ServiceRequest::create($data + [
                'reference' => ServiceRequest::newReference($data['service']),
                'status' => 'approved',
                'expires_at' => now()->addDays(5),
            ]);

        $request->session()->put('portal_request_reference', $entry->reference);

        if ($request->expectsJson()) {
            $request->session()->flash('tracking_reference', $entry->reference);
            $request->session()->flash('request_reference', $entry->reference);
            return response()->json([
                'request_code' => $entry->reference,
                'status' => $entry->service === 'testing' ? 'Pending Payment' : ucfirst($entry->status),
                'redirect_url' => route('portal.index', ['service' => $entry->service]) . '#track',
            ], 201);
        }

        return redirect(route('portal.index', ['service' => $entry->service]) . '#track')
            ->with('request_reference', $entry->reference)
            ->with('tracking_reference', $entry->reference);
    }
}
