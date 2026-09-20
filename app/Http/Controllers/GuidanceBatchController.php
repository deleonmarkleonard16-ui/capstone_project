<?php

namespace App\Http\Controllers;

use App\Models\GuidanceTestBatch;
use App\Services\GuidanceBatchService;
use App\Services\GuidanceCategories;
use App\Services\GuidanceQrService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuidanceBatchController extends Controller
{
    public function index(Request $request)
    {
        app(\App\Services\GuidanceAssessmentSessionService::class)->expireDue();
        $archived = $request->boolean('archived');
        $query = GuidanceTestBatch::with(['appointments.applicant', 'appointments.securityIncidents', 'makeupAppointments.applicant', 'makeupAppointments.securityIncidents', 'makeupAppointments.response'])->where('status', $archived ? '=' : '!=', 'Completed');
        $category = $request->route('module') ?? $request->query('category');
        $filters = \App\Support\TableFilters::validate($request, ['Pending Registration', 'In-Progress', 'Completed']);
        if ($filters['course'] ?? null) $query->where('course', $filters['course']);
        if ($filters['status'] ?? null) $query->where('status', $filters['status']);
        \App\Support\TableFilters::dates($query, $filters);
        foreach (\App\Support\TableFilters::words($filters['q'] ?? null) as $word) {
            $like = '%'.$word.'%';
            $query->where(fn ($q) => $q->where('batch_name', 'like', $like)->orWhere('year_section', 'like', $like)
                ->orWhereHas('appointments', fn ($a) => $a->where('student_id_number', 'like', $like)->orWhere('request_code', 'like', $like)->orWhereHas('applicant', fn ($s) => $s->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)))
                ->orWhereHas('makeupAppointments', fn ($a) => $a->where('student_id_number', 'like', $like)->orWhere('request_code', 'like', $like)->orWhereHas('applicant', fn ($s) => $s->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))));
        }
        if ($category) {
            $label = GuidanceCategories::LABELS[$category] ?? null;
            if ($label) {
                $query->where('test_type', $label);
            }
        }
        $batches = $query->latest('batch_id')->paginate(10)->withQueryString();
        return view('guidance.batches.index', ['batches' => $batches, 'archived' => $archived, 'moduleKey' => $category]);
    }
    public function store(Request $request, GuidanceBatchService $service)
    {
        $module = $request->route('module');
        $data = $request->validate([
            'batch_name' => 'required|string|max:255',
            'course' => \App\Support\CourseCatalog::rule(),
            'year_section' => 'nullable|string|max:50',
            'reason_for_request' => 'required|string|max:255',
            'test_type' => $module ? 'nullable|string' : ['required', Rule::in(array_values(GuidanceCategories::LABELS))],
            'roster' => 'required|file|max:2048|mimes:csv,txt'
        ]);
        if ($module) {
            abort_unless(isset(GuidanceCategories::LABELS[$module]), 404);
            $data['test_type'] = GuidanceCategories::LABELS[$module];
        }
        unset($data['roster']);
        try {
            $service->import($data, $request->file('roster'));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }
            return back()->withInput()->withErrors(['roster' => $e->getMessage()]);
        }
        return back()->with('success', 'Batch roster imported.');
    }
    public function action(Request $request, GuidanceTestBatch $batch, GuidanceBatchService $service)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['start', 'absent', 'convert', 'archive'])], 'appointment_id' => 'required_if:action,absent,convert|nullable|integer']);
        if ($data['action'] === 'start') $service->start($batch, $request->user()->id);
        else $service->action($batch, $data['action'], $data['appointment_id'] ?? null);
        return back()->with('success', 'Batch updated.');
    }
    public function qr(GuidanceTestBatch $batch, GuidanceQrService $qr)
    {
        $url = route('guidance.batch.join', $batch->batch_token);
        return view('guidance.batches.qr', ['batch' => $batch, 'url' => $url, 'image' => $qr->dataUri($url)]);
    }
    private function batch(string $token): GuidanceTestBatch
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        return GuidanceTestBatch::where('batch_token', $token)->firstOrFail();
    }
    private function verified(Request $request, GuidanceTestBatch $batch)
    {
        $identity = $request->session()->get('guidance_batch.'.$batch->getKey());
        abort_unless($identity && ($identity['until'] ?? 0) > time(), 403, 'Verify your roster identity again.');
        return $batch->appointments()->whereKey($identity['id'])->firstOrFail();
    }
    public function join(Request $request, string $token)
    {
        $batch = $this->batch($token);
        $appointment = null;
        $identity = $request->session()->get('guidance_batch.'.$batch->getKey());
        if ($identity && ($identity['until'] ?? 0) > time()) $appointment = $batch->appointments()->whereKey($identity['id'])->first();
        return view('guidance.batches.join', compact('batch', 'appointment'));
    }
    public function verify(Request $request, string $token, GuidanceBatchService $service)
    {
        $data = $request->validate(['student_id' => 'required|string|max:100', 'first_name' => 'required|string|max:100', 'middle_name' => 'nullable|string|max:100', 'last_name' => 'required|string|max:100']);
        $batch = $this->batch($token);
        $appointment = $service->verify($batch, $data);
        $request->session()->regenerate();
        $request->session()->put('guidance_batch.'.$batch->getKey(), ['id' => $appointment->getKey(), 'until' => time() + 14400]);
        return redirect()->route('guidance.batch.join', $token);
    }
    public function receipt(Request $request, string $token, GuidanceBatchService $service)
    {
        $request->validate(['receipt' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=8000,max_height=8000']);
        $batch = $this->batch($token);
        $service->receipt($batch, $this->verified($request, $batch)->getKey(), $request->file('receipt'));
        return redirect()->route('guidance.batch.join', $token);
    }
    public function state(Request $request, string $token)
    {
        $batch = $this->batch($token);
        $appointment = $this->verified($request, $batch);
        $url = $batch->status === 'In-Progress' && $appointment->attendance_status === 'Ready' && $appointment->qrCode?->is_active ? route('guidance.take', $appointment->qrCode->token) : null;
        return response()->json(['status' => $batch->status, 'attendance' => $appointment->attendance_status, 'url' => $url]);
    }
}
