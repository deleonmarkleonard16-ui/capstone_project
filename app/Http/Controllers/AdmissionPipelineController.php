<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Services\AdmissionScoringService;
use App\Support\CourseCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdmissionPipelineController extends Controller
{
    public function index()
    {
        return view('admin.admission.setup', ['cycles' => AdmissionCycle::latest()->get(), 'active' => AdmissionCycle::active(), 'courses' => CourseCatalog::activeOptions()]);
    }

    public function cycle(Request $request, ?AdmissionCycle $cycle = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'academic_year' => 'required|string|max:50',
            'passing_stanine' => 'required|integer|between:1,9', 'exam_weight' => 'required|numeric|between:0,100',
            'gwa_weight' => 'required|numeric|between:0,100', 'interview_weight' => 'required|numeric|between:0,100',
        ]);
        if (abs(array_sum(array_map('floatval', [$data['exam_weight'], $data['gwa_weight'], $data['interview_weight']])) - 100) > 0.01) {
            return back()->withErrors(['exam_weight' => 'Weights must total 100%.']);
        }
        $record = $cycle ?? new AdmissionCycle(['is_active' => false]);
        $record->fill($data)->save();
        app(AdmissionScoringService::class)->evaluate($record);
        return back()->with('success', 'Admission cycle saved.');
    }

    public function activate(AdmissionCycle $cycle)
    {
        abort_if($cycle->is_archived, 409);
        DB::transaction(function () use ($cycle) {
            AdmissionCycle::query()->update(['is_active' => false]);
            $cycle->update(['is_active' => true]);
        });
        return back()->with('success', 'Admission cycle activated.');
    }

    public function archive(AdmissionCycle $cycle)
    {
        $cycle->update(['is_active' => false, 'is_archived' => true]);
        return back()->with('success', 'Admission cycle archived.');
    }

    public function quota(Request $request)
    {
        $cycle = $this->active();
        $data = $request->validate(['course_code' => CourseCatalog::rule(), 'seats' => 'required|integer|min:0|max:100000']);
        DB::table('admission_course_quotas')->updateOrInsert(
            ['admission_cycle_id' => $cycle->id, 'course_code' => $data['course_code']],
            ['seats' => $data['seats'], 'updated_at' => now(), 'created_at' => now()]
        );
        app(AdmissionScoringService::class)->evaluate($cycle);
        return back()->with('success', 'Course quota updated.');
    }

    public function key(Request $request)
    {
        $cycle = $this->active();
        $data = $request->validate(['answers' => 'required|array|size:80', 'answers.*' => ['required', Rule::in(['A', 'B', 'C', 'D'])]]);
        DB::transaction(function () use ($cycle, $data) {
            foreach ($data['answers'] as $item => $answer) {
                DB::table('admission_answer_keys')->updateOrInsert(
                    ['admission_cycle_id' => $cycle->id, 'item_number' => (int) $item],
                    ['correct_answer' => $answer, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
        app(AdmissionScoringService::class)->rescoreCycle($cycle);
        return back()->with('success', 'Answer key saved.');
    }

    public function masterlist(Request $request)
    {
        $cycle = $this->active();
        $search = trim((string) $request->query('search', ''));
        $applicants = $cycle->applicants()->when($search, fn ($q) => $q->where(fn ($inner) => $inner->where('application_number', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")))->orderBy('last_name')->paginate(25)->withQueryString();
        return view('admin.admission.masterlist', compact('cycle', 'applicants', 'search') + ['courses' => CourseCatalog::activeOptions()]);
    }

    public function saveApplicant(Request $request, ?AdmissionApplicant $applicant = null, AdmissionScoringService $scoring)
    {
        $cycle = $this->active();
        if ($applicant) abort_unless($applicant->admission_cycle_id === $cycle->id, 404);
        $data = $request->validate([
            'application_number' => ['required', 'string', 'max:80', Rule::unique('admission_applicants')->ignore($applicant?->id)],
            'student_id' => 'nullable|string|max:80', 'first_name' => 'required|string|max:120', 'middle_name' => 'nullable|string|max:120',
            'last_name' => 'required|string|max:120', 'course_choice' => CourseCatalog::rule(), 'sex' => 'nullable|string|max:20',
            'special_group' => 'nullable|string|max:120', 'cmfl' => 'nullable|string|max:120',
            'gwa' => 'nullable|numeric|between:75,100', 'interview_score' => 'nullable|numeric|between:0,100',
        ]);
        $record = $applicant ?? new AdmissionApplicant();
        $record->fill($data);
        $record->admission_cycle_id = $cycle->id;
        $record->save();
        $scoring->evaluate($cycle);
        return redirect()->route('admin.admission.masterlist')->with('success', 'Applicant saved.');
    }

    public function import(Request $request, AdmissionScoringService $scoring, \App\Services\AdmissionSpreadsheetReader $reader)
    {
        $cycle = $this->active();
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:5120']);
        $path = $request->file('file')->getRealPath();
        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        try { $rows = $reader->rows($path, $ext); }
        catch (\RuntimeException $e) { return back()->withErrors(['file' => $e->getMessage()]); }
        $header = array_map(fn ($v) => strtolower(trim((string) $v)), array_shift($rows) ?: []);
        $required = ['student_id', 'first_name', 'middle_name', 'last_name', 'course', 'sex', '4ps_osy_ip_pwd_sp', 'cmfl', 'gwa'];
        if (array_diff($required, $header)) return back()->withErrors(['file' => 'Missing required columns: '.implode(', ', array_diff($required, $header))]);
        $count = 0; $errors = [];
        foreach ($rows as $values) {
            if (!array_filter($values, fn ($v) => trim((string) $v) !== '')) continue;
            $row = array_combine($header, array_slice(array_pad($values, count($header), ''), 0, count($header)));
            $course = CourseCatalog::normalizeLegacy($row['course']) ?? trim($row['course']);
            if (!isset(CourseCatalog::activeOptions()[$course])) { $errors[] = "Row ".($count + 2).": invalid course"; continue; }
            if (!is_numeric($row['gwa']) || $row['gwa'] < 75 || $row['gwa'] > 100) { $errors[] = "Row ".($count + 2).": invalid GWA"; continue; }
            $studentId = trim($row['student_id']);
            if ($studentId === '') { $errors[] = "Row ".($count + 2).": missing student ID"; continue; }
            if (trim($row['first_name']) === '' || trim($row['last_name']) === '') { $errors[] = "Row ".($count + 2).": missing name"; continue; }
            if (AdmissionApplicant::where('application_number', $studentId)->where('admission_cycle_id', '!=', $cycle->id)->exists()) { $errors[] = "Row ".($count + 2).": application number belongs to another cycle"; continue; }
            AdmissionApplicant::updateOrCreate(['application_number' => $studentId], [
                'admission_cycle_id' => $cycle->id, 'student_id' => $studentId, 'first_name' => trim($row['first_name']),
                'middle_name' => trim($row['middle_name']) ?: null, 'last_name' => trim($row['last_name']),
                'course_choice' => $course, 'sex' => trim($row['sex']), 'special_group' => trim($row['4ps_osy_ip_pwd_sp']),
                'cmfl' => trim($row['cmfl']), 'gwa' => $row['gwa'],
            ]);
            $count++;
        }
        $scoring->evaluate($cycle);
        return back()->with('success', "Imported {$count} applicants.")->with('import_errors', $errors);
    }

    public function issueToken(AdmissionApplicant $applicant)
    {
        abort_unless($applicant->admission_cycle_id === $this->active()->id, 404);
        abort_if($applicant->submitted_at, 409);
        $applicant->forceFill(['exam_token' => Str::random(64)])->save();
        return back()->with('success', 'Exam link issued: '.route('admission.take', $applicant->exam_token));
    }

    public function paper(AdmissionApplicant $applicant)
    {
        abort_unless($applicant->admission_cycle_id === $this->active()->id, 404);
        return view('admin.admission.paper', compact('applicant'));
    }

    public function scanner()
    {
        $this->active();
        return view('admin.admission.scanner');
    }

    public function scan(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:100']);
        $applicant = $this->active()->applicants()->where('application_number', $data['code'])->firstOrFail();
        return response()->json(['url' => route('admin.admission.encode', $applicant)]);
    }

    public function encode(AdmissionApplicant $applicant)
    {
        abort_unless($applicant->admission_cycle_id === $this->active()->id, 404);
        return view('admin.admission.encode', compact('applicant'));
    }

    public function submitPaper(Request $request, AdmissionApplicant $applicant, AdmissionScoringService $scoring)
    {
        abort_unless($applicant->admission_cycle_id === $this->active()->id, 404);
        $data = $request->validate(['answers' => 'required|array', 'answers.*' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])]]);
        $scoring->submit($applicant, $data['answers']);
        return redirect()->route('admin.admission.masterlist')->with('success', 'Paper answers scored.');
    }

    public function report(Request $request)
    {
        $cycle = $this->active();
        $data = $request->validate(['type' => ['required', Rule::in(['summary', 'qualified', 'not-qualified'])], 'course' => 'nullable|string|max:30', 'format' => ['nullable', Rule::in(['html', 'pdf', 'docx'])]]);
        $rows = $cycle->applicants()->when($data['course'] ?? null, fn ($q, $course) => $q->where('course_choice', $course))
            ->when($data['type'] !== 'summary', fn ($q) => $q->where('qualification_status', $data['type'] === 'qualified' ? 'Qualified' : 'Not Qualified'))
            ->orderBy('course_choice')->orderByDesc('total_score')->get();
        $html = view('admin.admission.report', compact('cycle', 'rows', 'data'))->render();
        if (($data['format'] ?? 'html') === 'pdf') return response($this->pdf($html))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="admission-report.pdf"');
        if (($data['format'] ?? 'html') === 'docx') return response(app(\App\Services\AdmissionDocxService::class)->render($cycle, $rows, $data['type']))->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')->header('Content-Disposition', 'attachment; filename="admission-report.docx"');
        return response($html);
    }

    private function pdf(string $html): string
    {
        $pdf = new \Dompdf\Dompdf(); $pdf->loadHtml($html); $pdf->setPaper('A4', 'landscape'); $pdf->render(); return $pdf->output();
    }

    private function active(): AdmissionCycle
    {
        return AdmissionCycle::active() ?? abort(409, 'Activate an admission cycle first.');
    }
}
