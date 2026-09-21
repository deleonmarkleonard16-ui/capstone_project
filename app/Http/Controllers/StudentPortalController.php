<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentPortalController extends Controller
{
    public function index()
    {
        $entry = session('portal_request_reference')
            ? ServiceRequest::where('reference', session('portal_request_reference'))->first()
            : null;
        return response()->view('portal.index', ['recentRequest' => $entry])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service' => ['required', Rule::in(array_keys(ServiceRequest::SERVICES))],
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'student_status' => ['required', Rule::in(['student', 'alumni'])],
            'student_number' => 'required_if:student_status,student|nullable|string|max:50',
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
        if (! empty($data['reason'])) {
            $data['purpose'] = $data['reason'] === 'Others' ? $data['other_reason'] : $data['reason'];
        }
        unset($data['reason'], $data['other_reason']);
        // Normalise text fields to uppercase so every view shows consistent caps.
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = mb_strtoupper(trim($data[$field]));
            }
        }
        $data['student_number'] = mb_strtoupper(trim($data['student_number'] ?? ''));

        // ── Check for duplicate active request ─────────────────────────────
        if (! empty($data['student_number'])) {
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
            ? app(\App\Services\GuidancePortalService::class)->create($data)
            : ServiceRequest::create($data + [
            'reference' => ServiceRequest::newReference($data['service']),
            'status' => 'approved',
            'expires_at' => now()->addDays(5),
        ]);
        $request->session()->put('portal_request_reference', $entry->reference);

        if ($request->expectsJson()) {
            $request->session()->flash('tracking_reference', $entry->reference);
            $request->session()->flash('request_reference', $entry->reference);
            return response()->json(['request_code' => $entry->reference,
                'status' => $entry->service === 'testing' ? 'Pending Payment' : ucfirst($entry->status),
                'redirect_url' => route('portal.index', ['service' => $entry->service]).'#track'], 201);
        }

        return redirect(route('portal.index', ['service' => $entry->service]).'#track')
            ->with('request_reference', $entry->reference)
            ->with('tracking_reference', $entry->reference);
    }

    public function track(Request $request)
    {
        $reference = strtoupper(trim((string) $request->input('reference')));
        if (preg_match('/^(?:G-[A-Z0-9]{4}|(?:GT|TR)-[A-F0-9]{32})$/D', $reference)) {
            return redirect('/portal?service=testing#track')->with('tracking_reference', $reference);
        }
        $request->merge(['reference' => is_string($request->input('reference')) ? strtoupper(trim($request->input('reference'))) : $request->input('reference')]);
        $data = $request->validate(['reference' => ServiceRequest::referenceRules()]);
        $data['reference'] = preg_match('/^(?:TR|GM|EF)-/i', $data['reference']) ? strtoupper($data['reference']) : strtolower($data['reference']);
        $entry = ServiceRequest::where('reference', $data['reference'])->first();
        if (! $entry) {
            return back()->withErrors(['reference' => 'No request matches that reference.'])->withInput($request->only('reference'));
        }

        $entry->checkAndApplyExpiration();

        return response()->view('portal.tracking', ['entry' => $entry])->header('Cache-Control', 'no-store');
    }

    public function inbox(Request $request)
    {
        ServiceRequest::expirePendingRequests();

        $service = $request->route('service');
        abort_unless(array_key_exists($service, ServiceRequest::SERVICES), 404);
        $query = ServiceRequest::where('service', $service)->with('guidanceAppointments');
        if ($test = $request->route('test')) {
            $query->whereJsonContains('tests', $test);
        }

        $view = match ($service) {
            'good-moral' => 'staff.good-moral',
            'exit-form'  => 'staff.exit-form',
            default      => 'staff.service-requests',
        };

        return view($view, [
            'title' => $test ? ucfirst($test).' Testing Request' : ServiceRequest::SERVICES[$service],
            'requests' => $query->latest()->paginate(15),
        ]);
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        if ($serviceRequest->service === 'testing') {
            return app(PsychologicalRequestController::class)->update($request, $serviceRequest);
        }
        return app(DocumentRequestController::class)->update($request, $serviceRequest);
    }
}
