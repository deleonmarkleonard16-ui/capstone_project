<?php

namespace App\Http\Controllers;

use App\Models\GuidanceTestBatch;
use App\Models\ServiceRequest;
use App\Services\GuidanceBatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentRequestController extends Controller
{
    public const MODULES = ['good-moral' => 'Good Moral', 'exit-form' => 'Exit Form'];

    private function module(Request $request): string
    {
        $module = $request->route('module');
        abort_unless(isset(self::MODULES[$module]), 404);
        return $module;
    }

    public function index(Request $request)
    {
        ServiceRequest::expirePendingRequests();
        $module = $this->module($request);
        $filters = \App\Support\TableFilters::validate($request, ['pending', 'proof_review', 'ready', 'completed', 'void', 'declined']);
        $archived = $request->route('mode') === 'archive';
        $query = ServiceRequest::where('service', $module)->whereNull('batch_id');
        if ($filters['course'] ?? null) $query->where('course', $filters['course']);
        if ($filters['status'] ?? null) $query->where('status', $filters['status']);
        \App\Support\TableFilters::dates($query, $filters);
        foreach (\App\Support\TableFilters::words($filters['q'] ?? null) as $word) {
            $like = '%'.$word.'%';
            $query->where(fn ($q) => $q->where('student_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('reference', 'like', $like));
        }
        $query->whereNull('archived_at', 'and', $archived);
        return view($module === 'good-moral' ? 'staff.good-moral' : 'staff.exit-form', [
            'requests' => $query->latest()->paginate(15)->withQueryString(), 'moduleKey' => $module,
            'mode' => $archived ? 'archive' : 'queue',
            'archivedBatches' => $archived ? $this->filterDocumentBatches(GuidanceTestBatch::where('module_type', self::MODULES[$module])->whereNotNull('archived_at'), $filters)->latest()->paginate(10, ['*'], 'batches_page')->withQueryString() : null,
        ]);
    }

    public function batches(Request $request)
    {
        $module = $this->module($request);
        $filters = \App\Support\TableFilters::validate($request, ['Pending Registration', 'In-Progress', 'Completed']);
        $archived = $request->boolean('archived');
        $batches = GuidanceTestBatch::where('module_type', self::MODULES[$module])
            ->whereNull('archived_at', 'and', $archived)
            ->when($filters['course'] ?? null, fn ($q, $course) => $q->where('course', $course));
        $batches = $this->filterDocumentBatches($batches, $filters)
            ->with('documentRequests')->latest('batch_id')->paginate(10)->withQueryString();
        return view('staff.document-batches', compact('module', 'batches', 'archived'));
    }

    private function filterDocumentBatches(\Illuminate\Database\Eloquent\Builder $query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        if ($filters['course'] ?? null) $query->where('course', $filters['course']);
        if ($filters['status'] ?? null) $query->where('status', $filters['status'] === 'completed' ? 'Completed' : $filters['status']);
        \App\Support\TableFilters::dates($query, $filters);
        foreach (\App\Support\TableFilters::words($filters['q'] ?? null) as $word) {
            $like = '%'.$word.'%';
            $query->where(fn ($q) => $q->where('batch_name', 'like', $like)->orWhere('year_section', 'like', $like)
                ->orWhereHas('documentRequests', fn ($entry) => $entry->where('student_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('reference', 'like', $like)));
        }
        return $query;
    }

    public function store(Request $request, GuidanceBatchService $rosters)
    {
        $module = $this->module($request);
        $data = $request->validate([
            'batch_name' => 'required|string|max:255', 'course' => \App\Support\CourseCatalog::rule(),
            'year_section' => 'nullable|string|max:50',
            'reason_for_request' => 'required|string|max:255',
            'roster' => 'required|file|max:2048|mimes:csv,txt',
        ]);
        $rows = $rosters->parseRoster($request->file('roster'));
        DB::transaction(function () use ($module, $data, $rows) {
            $batch = GuidanceTestBatch::create([
                'batch_token' => bin2hex(random_bytes(32)), 'batch_name' => $data['batch_name'],
                'course' => $data['course'], 'year_section' => $data['year_section'] ?? null, 'module_type' => self::MODULES[$module],
                'test_type' => null, 'reason_for_request' => $data['reason_for_request'],
                'status' => 'Pending Registration',
            ]);
            foreach ($rows as $row) {
                ServiceRequest::create([
                    'batch_id' => $batch->getKey(), 'reference' => ServiceRequest::newReference($module),
                    'service' => $module, 'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?? null, 'last_name' => $row['last_name'],
                    'student_status' => 'student', 'student_number' => $row['student_id'],
                    'course' => $data['course'], 'purpose' => $data['reason_for_request'],
                    'copies' => 1, 'status' => 'pending',
                ]);
            }
        }, 3);
        return redirect()->route(auth()->user()->role.'.'.$module.'.batches')->with('success', 'Document batch imported.');
    }

    public function analytics(Request $request)
    {
        $module = $this->module($request);
        $filters = $request->validate(['course' => ['nullable', Rule::in(array_keys(\App\Support\CourseCatalog::allOptions()))]]);
        $base = ServiceRequest::where('service', $module)->when($filters['course'] ?? null, fn ($q, $course) => $q->where('course', $course));
        $counts = (clone $base)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $monthly = (clone $base)->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("substr(created_at, 1, 7) AS month, COUNT(*) AS total")
            ->groupBy('month')->orderBy('month')->pluck('total', 'month');
        $courses = (clone $base)->whereNotNull('course')->selectRaw('course, COUNT(*) AS total')->groupBy('course')->pluck('total', 'course');
        return view('staff.document-analytics', compact('module', 'counts', 'monthly', 'courses'));
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless(isset(self::MODULES[$serviceRequest->service]), 404);
        abort_if($request->route('module') && $request->route('module') !== $serviceRequest->service, 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['verify', 'claim'])],
            'or_number' => 'required_if:action,claim|nullable|string|max:100',
            'or_date' => 'required_if:action,claim|nullable|date_format:Y-m-d|before_or_equal:today',
            'staff_message' => 'nullable|string|max:2000',
        ]);
        DB::transaction(function () use ($serviceRequest, $data) {
            $entry = ServiceRequest::whereKey($serviceRequest->getKey())->lockForUpdate()->firstOrFail();
            if ($data['action'] === 'verify') {
                // Guard: If already approved/ready, treat as idempotent success
                if (in_array($entry->status, ['ready', 'completed'], true)) {
                    return;
                }

                abort_unless($entry->status === 'proof_review' && $entry->hasReceipt(), 409, 'A submitted receipt is required for verification.');
                $entry->update(['status' => 'ready', 'staff_message' => $data['staff_message'] ?? 'Approved / Ready for Pickup']);
            } else {
                // Guard: If already claimed/completed, treat as idempotent success
                if ($entry->status === 'completed') {
                    return;
                }

                abort_unless($entry->status === 'ready', 409, 'Verify the receipt before marking this document claimed.');
                abort_unless($entry->hasReceipt(), 409, 'The verified receipt must be available before claiming.');
                $entry->update(['or_number' => $data['or_number'], 'or_date' => $data['or_date'], 'claimed_at' => now(), 'status' => 'completed', 'archived_at' => now(), 'staff_message' => $data['staff_message'] ?? 'Document claimed at the Guidance Office.']);
                if ($entry->batch_id) {
                    $batch = GuidanceTestBatch::whereKey($entry->batch_id)->lockForUpdate()->firstOrFail();
                    if (! $batch->documentRequests()->where('status', '!=', 'completed')->exists()) {
                        $batch->update(['status' => 'Completed', 'archived_at' => now()]);
                    }
                }
            }
        }, 3);

        $module = $serviceRequest->service;
        $serviceRequest->refresh();
        if (($data['action'] ?? null) === 'claim') {
            event(new \App\Events\DocumentRequestClaimed($serviceRequest, $module));
        }
        event(new \App\Events\ServiceRequestStatusChanged($serviceRequest, $module));

        $fallback = route(auth()->user()->role.'.'.$module);
        $previous = url()->previous();
        $redirectUrl = (! empty($previous) && ! str_contains($previous, '/notifications')) ? $previous : $fallback;

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Document request updated.',
                'redirect' => $redirectUrl,
            ]);
        }

        return redirect()->to($redirectUrl)->with('success', 'Document request updated.');
    }

    public function join(Request $request, string $token)
    {
        $batch = $this->documentBatch($token);
        $identity = $request->session()->get('document_batch.'.$batch->getKey());
        $id = ($identity['until'] ?? 0) > time() ? ($identity['id'] ?? null) : null;
        $entry = $id ? $batch->documentRequests()->whereKey($id)->first() : null;
        return view('staff.document-join', compact('batch', 'entry'));
    }

    public function verify(Request $request, string $token)
    {
        $data = $request->validate(['student_id' => 'required|string|max:100', 'first_name' => 'required|string|max:100', 'middle_name' => 'nullable|string|max:100', 'last_name' => 'required|string|max:100']);
        $batch = $this->documentBatch($token);
        abort_unless($batch->status === 'Pending Registration', 409, 'Batch registration is closed.');
        $entry = $batch->documentRequests()->where('student_number', mb_strtoupper(trim($data['student_id'])))->first();
        $matches = $entry !== null;
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $matches = $matches && mb_strtoupper(trim((string) ($data[$field] ?? ''))) === mb_strtoupper(trim((string) ($entry->$field ?? '')));
        }
        if (! $matches) throw ValidationException::withMessages(['student_id' => 'Student ID and full name must exactly match the CSV roster.']);
        $request->session()->regenerate();
        $request->session()->put('document_batch.'.$batch->getKey(), ['id' => $entry->getKey(), 'until' => time() + 14400]);
        return redirect()->route('document.batch.join', $token);
    }

    public function receipt(Request $request, string $token)
    {
        $file = $request->file('receipt') ?? $request->file('payment_slip') ?? $request->file('proof');
        if ($file) {
            $request->files->set('receipt', $file);
            $request->files->set('payment_slip', $file);
            $request->files->set('proof', $file);
        }
        $request->validate(
            ['receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120'],
            [
                'receipt.required' => 'Please select a valid image file under 5 MB.',
                'receipt.image' => 'Please upload a valid image file (JPG, PNG, WebP).',
                'receipt.mimes' => 'Please upload a valid image file (JPG, PNG, WebP).',
                'receipt.max' => 'The receipt file size must not exceed 5 MB.',
            ]
        );
        $batch = $this->documentBatch($token);
        abort_unless($batch->status === 'Pending Registration', 409, 'Batch registration is closed.');
        $identity = $request->session()->get('document_batch.'.$batch->getKey());
        $id = ($identity['until'] ?? 0) > time() ? ($identity['id'] ?? null) : null;
        abort_unless($id, 403, 'Unauthorized access.');
        $receipt = \App\Services\ReceiptStorage::payload($request->file('receipt'));
        DB::transaction(function () use ($batch, $id, $receipt) {
                $entry = $batch->documentRequests()->whereKey($id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($entry->status, ['pending', 'approved'], true), 409, 'This request is not awaiting payment.');
                $entry->update([...$receipt, 'proof_path' => null, 'status' => 'proof_review']);
            }, 3);

        $message = 'Receipt uploaded successfully! Your payment is now pending verification by Guidance Staff.';
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => 'Receipt Uploaded / Pending Verification',
                'message' => $message,
            ]);
        }
        return redirect()->route('document.batch.join', $token)->with('success', $message);
    }

    public function proof(ServiceRequest $serviceRequest)
    {
        abort_unless(isset(self::MODULES[$serviceRequest->service]), 404);
        return \App\Services\ReceiptStorage::response($serviceRequest);
    }

    private function documentBatch(string $token): GuidanceTestBatch
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        return GuidanceTestBatch::where('batch_token', $token)->whereIn('module_type', self::MODULES)->firstOrFail();
    }
}
