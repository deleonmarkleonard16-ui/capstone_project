<?php

namespace App\Http\Controllers;

use App\Models\AdmissionCycle;
use App\Services\AdmissionScoringService;
use App\Support\CourseCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdmissionCycleController extends Controller
{
    public function settings()
    {
        $active = AdmissionCycle::active();
        $cycles = AdmissionCycle::withCount('applicants')->latest('id')->get();
        $courses = CourseCatalog::activeOptions();

        return view('admin.admission.setup', compact('active', 'cycles', 'courses'));
    }

    public function store(Request $request, AdmissionScoringService $scoring)
    {
        return $this->saveCycle($request, null, $scoring);
    }

    public function update(Request $request, AdmissionCycle $cycle, AdmissionScoringService $scoring)
    {
        return $this->saveCycle($request, $cycle, $scoring);
    }

    public function saveCycle(Request $request, ?AdmissionCycle $cycle = null, ?AdmissionScoringService $scoring = null)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'cycle_name' => 'nullable|string|max:120',
            'academic_year' => 'nullable|string|max:50',
            'status' => ['nullable', Rule::in(array_merge(AdmissionCycle::STATUSES, ['Maintenance', 'Archived']))],
            'passing_stanine' => 'nullable|integer|between:1,9',
            'total_items' => 'nullable|integer|between:10,200',
            'exam_weight' => 'nullable|numeric|between:0,100',
            'gwa_weight' => 'nullable|numeric|between:0,100',
            'interview_weight' => 'nullable|numeric|between:0,100',
            'set_active' => 'nullable|boolean',
        ]);

        $cycleName = trim($data['cycle_name'] ?? ($data['name'] ?? ''));
        if ($cycleName === '') {
            return back()->withErrors(['cycle_name' => 'Cycle name is required.']);
        }

        $academicYear = trim($data['academic_year'] ?? '');
        if ($academicYear === '') {
            if (preg_match('/(\d{4})\s*[-–]\s*(\d{4})/', $cycleName, $matches)) {
                $academicYear = "{$matches[1]}-{$matches[2]}";
            } else {
                $academicYear = date('Y') . '-' . (date('Y') + 1);
            }
        }

        $examWeight = (float) ($data['exam_weight'] ?? 60.00);
        $gwaWeight = (float) ($data['gwa_weight'] ?? 20.00);
        $interviewWeight = (float) ($data['interview_weight'] ?? 20.00);

        if (abs(($examWeight + $gwaWeight + $interviewWeight) - 100) > 0.01) {
            return back()->withErrors(['exam_weight' => 'Weights must total 100%.']);
        }

        $record = $cycle ?? new AdmissionCycle();
        $record->name = $cycleName;
        $record->cycle_name = $cycleName;
        $record->academic_year = $academicYear;
        $record->passing_stanine = (int) ($data['passing_stanine'] ?? 4);
        $record->total_items = (int) ($data['total_items'] ?? ($record->total_items ?: 80));
        $record->exam_weight = $examWeight;
        $record->gwa_weight = $gwaWeight;
        $record->interview_weight = $interviewWeight;

        $targetStatus = $data['status'] ?? ($record->status ?: AdmissionCycle::STATUS_DRAFT);
        $shouldActivate = $request->boolean('set_active') || $targetStatus === AdmissionCycle::STATUS_ACTIVE;
        $isMaintenance  = $targetStatus === AdmissionCycle::STATUS_MAINTENANCE;

        if ($shouldActivate) {
            $record->save();
            $record->activate();
        } elseif ($isMaintenance) {
            $record->status    = AdmissionCycle::STATUS_MAINTENANCE;
            $record->is_active = true;
            $record->save();
        } else {
            $record->status    = $targetStatus;
            $record->is_active = false;
            $record->save();
        }

        if ($scoring) {
            $scoring->evaluate($record);
        } else {
            app(AdmissionScoringService::class)->evaluate($record);
        }

        $msg = match(true) {
            $shouldActivate  => "Admission cycle '{$record->displayName}' initialized with {$record->total_items} items and set as Active.",
            $isMaintenance   => "Admission cycle '{$record->displayName}' placed in Maintenance Mode.",
            default          => "Admission cycle '{$record->displayName}' saved with {$record->total_items} exam items.",
        };

        return back()->with('success', $msg);
    }

    public function activate(AdmissionCycle $cycle)
    {
        abort_if($cycle->isCompleted(), 409, 'Cannot activate an archived/completed cycle.');
        $cycle->activate();

        return redirect()->route('admin.admission.masterlist', ['cycle_id' => $cycle->id])
            ->with('success', "Admission cycle '{$cycle->displayName}' is now active.");
    }

    public function archive(AdmissionCycle $cycle)
    {
        $cycle->completeAndArchive();
        return back()->with('success', "Admission cycle '{$cycle->displayName}' marked as Completed / Archived.");
    }

    public function complete(AdmissionCycle $cycle)
    {
        $cycle->completeAndArchive();
        return redirect()->route('admin.admission.index')
            ->with('success', "Admission cycle '{$cycle->displayName}' marked as Completed / Archived.");
    }
}
