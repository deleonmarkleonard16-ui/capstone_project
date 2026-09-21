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
        return view('admin.admission.setup', [
            'cycles' => AdmissionCycle::latest()->get(),
            'active' => AdmissionCycle::active(),
            'courses' => CourseCatalog::activeOptions(),
        ]);
    }

    public function cycle(Request $request, ?AdmissionCycle $cycle = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'academic_year' => 'required|string|max:50',
            'passing_stanine' => 'required|integer|between:1,9',
            'exam_weight' => 'required|numeric|between:0,100',
            'gwa_weight' => 'required|numeric|between:0,100',
            'interview_weight' => 'required|numeric|between:0,100',
        ]);

        if (abs(array_sum(array_map('floatval', [$data['exam_weight'], $data['gwa_weight'], $data['interview_weight']])) - 100) > 0.01) {
            return back()->withErrors(['exam_weight' => 'Weights must total 100%.']);
        }

        $record = $cycle ?? new AdmissionCycle(['is_active' => false]);
        $record->fill($data)->save();
        app(AdmissionScoringService::class)->evaluate($record);

        return back()->with('success', 'Admission cycle saved successfully.');
    }

    public function activate(AdmissionCycle $cycle)
    {
        abort_if($cycle->is_archived, 409);
        DB::transaction(function () use ($cycle) {
            AdmissionCycle::query()->update(['is_active' => false]);
            $cycle->update(['is_active' => true]);
        });

        return redirect()->route('admin.admission.masterlist')->with('success', "Admission cycle '{$cycle->name}' is now active.");
    }

    public function archive(AdmissionCycle $cycle)
    {
        $cycle->update(['is_active' => false, 'is_archived' => true]);
        return back()->with('success', 'Admission cycle archived.');
    }

    public function quota(Request $request)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        $data = $request->validate([
            'course_code' => CourseCatalog::rule(),
            'seats' => 'required|integer|min:0|max:100000',
        ]);

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
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        $data = $request->validate([
            'answers' => 'required|array:' . implode(',', range(1, 80)) . '|size:80',
            'answers.*' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        DB::transaction(function () use ($cycle, $data) {
            foreach ($data['answers'] as $item => $answer) {
                DB::table('admission_answer_keys')->updateOrInsert(
                    ['admission_cycle_id' => $cycle->id, 'item_number' => (int) $item],
                    ['correct_answer' => $answer, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        app(AdmissionScoringService::class)->rescoreCycle($cycle);
        return back()->with('success', 'Answer key saved and cycle re-scored.');
    }

    public function masterlist(Request $request)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        $search = trim((string) $request->query('search', ''));
        $batchGroup = trim((string) $request->query('batch_group', ''));
        $course = trim((string) $request->query('course', ''));
        $sessionFilter = trim((string) $request->query('session_filter', ''));
        $examFilter = trim((string) $request->query('exam_filter', ''));
        $interviewFilter = trim((string) $request->query('interview_filter', ''));
        $stanine = $request->query('stanine');
        $sort = $request->query('sort', 'course_last_name');

        $query = $cycle->applicants();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('application_number', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('course_choice', 'like', "%{$search}%");
            });
        }

        if ($batchGroup !== '' && $batchGroup !== '__all__') {
            $query->where('batch_group', $batchGroup);
        }

        if ($course !== '') {
            $query->where('course_choice', $course);
        }

        if ($sessionFilter !== '') {
            $query->where('session_label', $sessionFilter);
        }

        if ($examFilter === 'submitted') {
            $query->whereNotNull('submitted_at');
        } elseif ($examFilter === 'not_submitted') {
            $query->whereNull('submitted_at');
        }

        if ($interviewFilter === 'scored') {
            $query->whereNotNull('interview_score');
        } elseif ($interviewFilter === 'pending') {
            $query->whereNull('interview_score');
        }

        if ($stanine !== null && $stanine !== '') {
            $query->where('stanine_score', (int) $stanine);
        }

        // Sorting logic
        match ($sort) {
            'gwa_desc' => $query->orderByDesc('gwa'),
            'total_desc' => $query->orderByDesc('total_score'),
            'last_name' => $query->orderBy('last_name')->orderBy('first_name'),
            default => $query->orderBy('course_choice')->orderBy('last_name'),
        };

        $applicants = $query->paginate(25)->withQueryString();

        // Distinct batch groups and sessions for dropdowns
        $batchGroups = $cycle->applicants()->whereNotNull('batch_group')->distinct()->pluck('batch_group')->filter()->values()->all();
        if (empty($batchGroups)) {
            $batchGroups = ['Batch 1', 'Batch 2', 'Batch 3', 'Walk-in'];
        }

        $sessionOptions = $cycle->applicants()->whereNotNull('session_label')->distinct()->pluck('session_label')->filter()->values()->all();
        if (empty($sessionOptions)) {
            $sessionOptions = ['First Batch - Session A', 'First Batch - Session B', 'Second Batch - Session A'];
        }

        return view('admin.admission.masterlist', [
            'cycle' => $cycle,
            'applicants' => $applicants,
            'search' => $search,
            'courses' => CourseCatalog::activeOptions(),
            'batchGroups' => $batchGroups,
            'sessionOptions' => $sessionOptions,
        ]);
    }

    public function encodingSheet(Request $request)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        $batchGroup = trim((string) $request->query('batch_group', ''));
        $sort = $request->query('sort');

        $query = $cycle->applicants();
        if ($batchGroup !== '' && $batchGroup !== '__all__') {
            $query->where('batch_group', $batchGroup);
        }

        if ($sort === 'course_gwa') {
            $query->orderBy('course_choice')->orderByDesc('gwa');
        } else {
            $query->orderBy('id', 'asc');
        }

        $rows = $query->get();

        $batchGroups = $cycle->applicants()->whereNotNull('batch_group')->distinct()->pluck('batch_group')->filter()->values()->all();
        if (empty($batchGroups)) {
            $batchGroups = ['Batch 1', 'Batch 2', 'Batch 3', 'Walk-in'];
        }

        return view('admin.admission.encoding-sheet', [
            'cycle' => $cycle,
            'rows' => $rows,
            'batchGroups' => $batchGroups,
            'courses' => CourseCatalog::activeOptions(),
        ]);
    }

    public function saveEncodingSheet(Request $request, AdmissionScoringService $scoring)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        $inputRows = $request->input('rows', []);
        $batchGroup = trim((string) $request->input('batch_group', ''));
        $savedCount = 0;

        DB::transaction(function () use ($inputRows, $cycle, $batchGroup, &$savedCount) {
            foreach ($inputRows as $row) {
                $lastName = trim((string) ($row['last_name'] ?? ''));
                $firstName = trim((string) ($row['first_name'] ?? ''));

                // Skip rows without at least last and first name
                if ($lastName === '' || $firstName === '') {
                    continue;
                }

                $middleName = trim((string) ($row['middle_name'] ?? '')) ?: null;
                $courseChoice = trim((string) ($row['course_choice'] ?? ''));
                $sex = trim((string) ($row['sex'] ?? '')) ?: null;
                $specialGroup = trim((string) ($row['special_group'] ?? '')) ?: null;
                $cmfl = trim((string) ($row['cmfl'] ?? '')) ?: null;
                $gwa = isset($row['gwa']) && is_numeric($row['gwa']) ? (float) $row['gwa'] : null;

                $id = $row['id'] ?? null;
                if ($id) {
                    $applicant = AdmissionApplicant::where('id', $id)->where('admission_cycle_id', $cycle->id)->first();
                } else {
                    $applicant = null;
                }

                if (!$applicant) {
                    $yearPrefix = date('y');
                    $randomNum = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                    $appNum = "CAT-{$yearPrefix}-{$randomNum}";
                    while (AdmissionApplicant::where('application_number', $appNum)->exists()) {
                        $appNum = "CAT-{$yearPrefix}-" . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                    }

                    $applicant = new AdmissionApplicant([
                        'admission_cycle_id' => $cycle->id,
                        'application_number' => $appNum,
                    ]);
                }

                $applicant->last_name = $lastName;
                $applicant->first_name = $firstName;
                $applicant->middle_name = $middleName;
                if ($courseChoice !== '') {
                    $applicant->course_choice = $courseChoice;
                }
                $applicant->sex = $sex;
                $applicant->special_group = $specialGroup;
                $applicant->cmfl = $cmfl;
                $applicant->gwa = $gwa;

                if ($batchGroup !== '' && $batchGroup !== '__all__') {
                    $applicant->batch_group = $batchGroup;
                }

                $applicant->save();
                $savedCount++;
            }
        });

        $scoring->evaluate($cycle);

        return redirect()->route('admin.admission.encoding-sheet', ['batch_group' => $batchGroup])
            ->with('success', "Successfully saved {$savedCount} row(s) to the Masterlist Encoding Sheet.");
    }

    public function saveApplicant(Request $request, ?AdmissionApplicant $applicant = null, AdmissionScoringService $scoring)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        if ($applicant) abort_unless($applicant->admission_cycle_id === $cycle->id, 404);
        $data = $request->validate([
            'application_number' => ['required', 'string', 'max:80', Rule::unique('admission_applicants')->ignore($applicant?->id)],
            'student_id' => 'nullable|string|max:80',
            'first_name' => 'required|string|max:120',
            'middle_name' => 'nullable|string|max:120',
            'last_name' => 'required|string|max:120',
            'course_choice' => CourseCatalog::rule(),
            'sex' => 'nullable|string|max:20',
            'special_group' => 'nullable|string|max:120',
            'cmfl' => 'nullable|string|max:120',
            'gwa' => 'nullable|numeric|between:75,100',
            'interview_score' => 'nullable|numeric|between:0,100',
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
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:5120']);
        $batchGroup = trim((string) $request->input('batch_group', ''));

        $path = $request->file('file')->getRealPath();
        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        try {
            $rows = $reader->rows($path, $ext);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $header = array_map(fn ($v) => strtolower(trim(ltrim((string) $v, "\xEF\xBB\xBF"))), array_shift($rows) ?: []);
        if (count($header) !== count(array_unique($header))) {
            return back()->withErrors(['file' => 'Duplicate column headings are not allowed.']);
        }

        $required = ['application_number', 'first_name', 'middle_name', 'last_name', 'course', 'sex', '4ps_osy_ip_pwd_sp', 'cmfl', 'gwa'];
        if (array_diff($required, $header)) {
            return back()->withErrors(['file' => 'Missing required columns: ' . implode(', ', array_diff($required, $header))]);
        }

        $count = 0;
        $errors = [];
        $courses = CourseCatalog::activeOptions();

        foreach ($rows as $rowIndex => $values) {
            if (!array_filter($values, fn ($v) => trim((string) $v) !== '')) continue;
            $row = array_combine($header, array_slice(array_pad($values, count($header), ''), 0, count($header)));
            $validator = validator($row, [
                'application_number' => 'required|string|max:80',
                'first_name' => 'required|string|max:120',
                'last_name' => 'required|string|max:120',
                'middle_name' => 'nullable|string|max:120',
                'sex' => 'nullable|string|max:20',
                '4ps_osy_ip_pwd_sp' => 'nullable|string|max:120',
                'cmfl' => 'nullable|string|max:120',
            ]);
            if ($validator->fails()) {
                $errors[] = 'Row ' . ($rowIndex + 2) . ': ' . $validator->errors()->first();
                continue;
            }

            $course = CourseCatalog::normalizeLegacy($row['course']) ?? trim($row['course']);
            if (!isset($courses[$course])) {
                $errors[] = "Row " . ($rowIndex + 2) . ": invalid course ({$row['course']})";
                continue;
            }
            if (!is_numeric($row['gwa']) || $row['gwa'] < 75 || $row['gwa'] > 100) {
                $errors[] = "Row " . ($rowIndex + 2) . ": invalid GWA";
                continue;
            }

            $studentId = trim($row['application_number']);
            if ($studentId === '') {
                $errors[] = "Row " . ($rowIndex + 2) . ": missing application number";
                continue;
            }

            if (AdmissionApplicant::where('application_number', $studentId)->where('admission_cycle_id', '!=', $cycle->id)->exists()) {
                $errors[] = "Row " . ($rowIndex + 2) . ": application number belongs to another cycle";
                continue;
            }

            AdmissionApplicant::updateOrCreate(['application_number' => $studentId], [
                'admission_cycle_id' => $cycle->id,
                'batch_group' => $batchGroup ?: null,
                'student_id' => null,
                'first_name' => trim($row['first_name']),
                'middle_name' => trim($row['middle_name']) ?: null,
                'last_name' => trim($row['last_name']),
                'course_choice' => $course,
                'sex' => trim($row['sex']),
                'special_group' => trim($row['4ps_osy_ip_pwd_sp']),
                'cmfl' => trim($row['cmfl']),
                'gwa' => $row['gwa'],
            ]);
            $count++;
        }

        $scoring->evaluate($cycle);
        return back()->with('success', "Imported {$count} applicants.")->with('import_errors', $errors);
    }

    public function issueToken(AdmissionApplicant $applicant)
    {
        $cycle = $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();
        abort_unless($applicant->admission_cycle_id === $cycle->id, 404);
        abort_if($applicant->submitted_at, 409);
        $applicant->forceFill(['exam_token' => Str::random(64)])->save();
        return back()->with('success', 'Exam link issued: ' . route('admission.take', $applicant->exam_token));
    }

    public function paper(AdmissionApplicant $applicant)
    {
        $cycle = $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();
        abort_unless($applicant->admission_cycle_id === $cycle->id, 404);
        return view('admin.admission.paper', compact('applicant'));
    }

    public function scanner()
    {
        $cycle = $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();
        return view('admin.admission.scanner');
    }

    public function scan(Request $request)
    {
        $cycle = $this->active();
        if (!$cycle) return response()->json(['error' => 'No active cycle'], 409);
        $data = $request->validate(['code' => 'required|string|max:100']);
        $applicant = $cycle->applicants()->where('application_number', $data['code'])->firstOrFail();
        abort_if($applicant->submitted_at, 409, 'This applicant has already submitted an exam.');
        return response()->json([
            'url' => route('admin.admission.encode', $applicant),
            'name' => $applicant->full_name,
            'application_number' => $applicant->application_number,
            'course' => CourseCatalog::label($applicant->course_choice),
        ]);
    }

    public function encode(AdmissionApplicant $applicant)
    {
        $cycle = $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();
        abort_unless($applicant->admission_cycle_id === $cycle->id, 404);
        return view('admin.admission.encode', compact('applicant'));
    }

    public function submitPaper(Request $request, AdmissionApplicant $applicant, AdmissionScoringService $scoring)
    {
        $cycle = $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();
        abort_unless($applicant->admission_cycle_id === $cycle->id, 404);
        $data = $request->validate([
            'answers' => 'required|array:' . implode(',', range(1, 80)) . '|size:80',
            'answers.*' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
        ]);
        $scoring->submit($applicant, $data['answers']);
        return redirect()->route('admin.admission.masterlist')->with('success', 'Paper answers scored.');
    }

    public function report(Request $request)
    {
        $cycle = $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();
        $data = $request->validate([
            'type' => ['required', Rule::in(['summary', 'qualified', 'not-qualified'])],
            'course' => 'nullable|string|max:30',
            'format' => ['nullable', Rule::in(['html', 'pdf', 'docx'])],
        ]);

        $rows = $cycle->applicants()
            ->when($data['course'] ?? null, fn ($q, $course) => $q->where('course_choice', $course))
            ->when($data['type'] !== 'summary', fn ($q) => $q->where('qualification_status', $data['type'] === 'qualified' ? 'Qualified' : 'Not Qualified'))
            ->orderBy('course_choice')
            ->orderByDesc('total_score')
            ->get();

        $html = view('admin.admission.report', compact('cycle', 'rows', 'data'))->render();

        if (($data['format'] ?? 'html') === 'pdf') {
            return response($this->pdf($html))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="admission-report.pdf"');
        }

        if (($data['format'] ?? 'html') === 'docx') {
            return response(app(\App\Services\AdmissionDocxService::class)->render($cycle, $rows, $data['type']))
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                ->header('Content-Disposition', 'attachment; filename="admission-report.docx"');
        }

        return response($html);
    }

    private function pdf(string $html): string
    {
        $pdf = new \Dompdf\Dompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        return $pdf->output();
    }

    private function active(): ?AdmissionCycle
    {
        return AdmissionCycle::active();
    }

    private function gatekeeperRedirect()
    {
        return redirect()->route('admin.admission.index')->with('warning', 'Please select or initialize an active Admission Cycle (e.g., S.Y. 2026 – 2027) before accessing admission records.');
    }
}
