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
            'cycles' => AdmissionCycle::withCount('applicants')->latest('id')->get(),
            'active' => AdmissionCycle::active(),
            'courses' => CourseCatalog::activeOptions(),
        ]);
    }

    public function cycle(Request $request, ?AdmissionCycle $cycle = null)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'cycle_name' => 'nullable|string|max:120',
            'academic_year' => 'nullable|string|max:50',
            'status' => ['nullable', Rule::in(array_merge(AdmissionCycle::STATUSES, ['Maintenance', 'Archived']))],
            'passing_stanine' => 'nullable|integer|between:1,9',
            'stanine_cutoff_board' => 'nullable|integer|between:1,9',
            'stanine_board' => 'nullable|integer|between:1,9',
            'stanine_cutoff_non_board' => 'nullable|integer|between:1,9',
            'stanine_nonboard' => 'nullable|integer|between:1,9',
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
            // Extract academic year from cycleName (e.g., "S.Y. 2026 - 2027" -> "2026-2027")
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

        $boardCutoff = $data['stanine_board'] ?? ($data['stanine_cutoff_board'] ?? null);
        if ($boardCutoff !== null) {
            $record->stanine_cutoff_board = (int) $boardCutoff;
        }
        $nonBoardCutoff = $data['stanine_nonboard'] ?? ($data['stanine_cutoff_non_board'] ?? null);
        if ($nonBoardCutoff !== null) {
            $record->stanine_cutoff_non_board = (int) $nonBoardCutoff;
        }
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
            // Maintenance: cycle remains findable by active() but gatekeeper blocks encoding
            $record->status    = AdmissionCycle::STATUS_MAINTENANCE;
            $record->is_active = true;
            $record->save();
        } else {
            $record->status    = $targetStatus;
            $record->is_active = false;
            $record->save();
        }

        app(AdmissionScoringService::class)->evaluate($record);

        $msg = match(true) {
            $shouldActivate  => "Admission cycle '{$record->displayName}' initialized with {$record->total_items} items and set as Active.",
            $isMaintenance   => "Admission cycle '{$record->displayName}' placed in Maintenance Mode. Encoding and masterlist access is suspended.",
            default          => "Admission cycle '{$record->displayName}' saved with {$record->total_items} items.",
        };

        return back()->with('success', $msg);
    }

    public function activate(AdmissionCycle $cycle)
    {
        $cycle->activate();

        return redirect()->route('admin.admission.masterlist', ['cycle_id' => $cycle->id])
            ->with('success', "Admission cycle '{$cycle->displayName}' is now active.");
    }

    public function archive(AdmissionCycle $cycle)
    {
        $cycle->completeAndArchive();
        return back()->with('success', "Admission cycle '{$cycle->displayName}' has been marked as Completed and Archived.");
    }

    public function complete(AdmissionCycle $cycle)
    {
        $cycle->completeAndArchive();
        return redirect()->route('admin.admission.index')
            ->with('success', "Admission cycle '{$cycle->displayName}' marked as Completed / Archived. Records are locked from modifications.");
    }

    public function quota(Request $request)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        abort_if($cycle->isCompleted(), 422, 'Cannot edit quotas on an archived cycle.');

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

    public function answerKey()
    {
        return view('admin.admission.answer-key', [
            'active' => AdmissionCycle::active(),
        ]);
    }

    public function key(Request $request)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        abort_if($cycle->isCompleted(), 422, 'Cannot edit answer key on an archived cycle.');

        $totalItems = (int) ($request->input('total_items') ?: ($cycle->total_items ?: 80));
        $request->merge(['total_items' => $totalItems]);

        $data = $request->validate([
            'total_items' => 'required|integer|min:10|max:200',
            'answers' => 'required|array:' . implode(',', range(1, $totalItems)) . '|size:' . $totalItems,
            'answers.*' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        DB::transaction(function () use ($cycle, $totalItems, $data) {
            if ($cycle->total_items !== $totalItems) {
                $cycle->total_items = $totalItems;
                $cycle->save();
            }

            // Prune excess items beyond dynamic count
            DB::table('admission_answer_keys')
                ->where('admission_cycle_id', $cycle->id)
                ->where('item_number', '>', $totalItems)
                ->delete();

            // Sync answer key items 1..total_items
            foreach ($data['answers'] as $item => $answer) {
                DB::table('admission_answer_keys')->updateOrInsert(
                    ['admission_cycle_id' => $cycle->id, 'item_number' => (int) $item],
                    ['correct_answer' => $answer, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        app(AdmissionScoringService::class)->rescoreCycle($cycle);
        return back()->with('success', "Answer key for {$totalItems} items saved and cycle re-scored.");
    }

    public function masterlist(Request $request)
    {
        $allCycles = AdmissionCycle::orderByDesc('id')->get();

        // Allow inspecting archived/historical cycles via dropdown
        if ($request->filled('cycle_id')) {
            $cycle = AdmissionCycle::find($request->query('cycle_id'));
        } else {
            $cycle = $this->active();
        }

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

        // Assign ranks across the entire cycle before applying display filters.
        $ranks = $cycle->applicants()->orderByDesc('total_score')
            ->orderByDesc('stanine_score')->orderByDesc('gwa')
            ->orderByDesc('interview_score')->orderBy('id')
            ->pluck('id')->flip()->map(fn ($index) => $index + 1);

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

        if ($stanine === 'below_3') {
            $query->where('stanine_score', '<', 3);
        } elseif ($stanine === 'at_least_3') {
            $query->where('stanine_score', '>=', 3);
        } elseif ($stanine !== null && $stanine !== '') {
            $query->where('stanine_score', (int) $stanine);
        }

        $query->orderByDesc('total_score')->orderByDesc('stanine_score')
            ->orderByDesc('gwa')->orderByDesc('interview_score')->orderBy('id');

        $applicants = $query->paginate(25)->withQueryString();

        // Batch groups and session options
        $batchGroups = $cycle->applicants()->whereNotNull('batch_group')->distinct()->pluck('batch_group')->filter()->values()->all();
        if (empty($batchGroups)) {
            $batchGroups = ['Batch 1', 'Batch 2', 'Batch 3', 'Walk-in'];
        }

        $sessionOptions = $cycle->applicants()->whereNotNull('session_label')->distinct()->pluck('session_label')->filter()->values()->all();
        if (empty($sessionOptions)) {
            $sessionOptions = ['First Batch - Session A', 'First Batch - Session B', 'Second Batch - Session A'];
        }

        $isLocked = $cycle->isCompleted();

        return view('admin.admission.masterlist', [
            'cycle' => $cycle,
            'allCycles' => $allCycles,
            'isLocked' => $isLocked,
            'applicants' => $applicants,
            'ranks' => $ranks,
            'search' => $search,
            'courses' => CourseCatalog::allOptions(),
            'reportPrograms' => array_unique(array_merge(array_keys(CourseCatalog::allOptions()),
                $cycle->applicants()->distinct()->pluck('course_choice')->all())),
            'reportCutoffs' => app(\App\Services\AdmissionReportService::class)->cutoffs($cycle),
            'batchGroups' => $batchGroups,
            'sessionOptions' => $sessionOptions,
        ]);
    }

    public function encodingSheet(Request $request)
    {
        $allCycles = AdmissionCycle::orderByDesc('id')->get();

        if ($request->filled('cycle_id')) {
            $cycle = AdmissionCycle::find($request->query('cycle_id'));
        } else {
            $cycle = $this->active();
        }

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

        $isLocked = $cycle->isCompleted();

        return view('admin.admission.encoding-sheet', [
            'cycle' => $cycle,
            'allCycles' => $allCycles,
            'isLocked' => $isLocked,
            'rows' => $rows,
            'batchGroups' => $batchGroups,
            'courses' => CourseCatalog::activeOptions(),
        ]);
    }

    public function saveEncodingSheet(Request $request, AdmissionScoringService $scoring)
    {
        $cycleId = $request->input('cycle_id');
        $cycle = $cycleId ? AdmissionCycle::find($cycleId) : $this->active();

        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        abort_if($cycle->isCompleted(), 422, 'This admission cycle is archived/completed and locked from new applicant encoding.');

        $inputRows = $request->input('rows', []);
        $batchGroup = trim((string) $request->input('batch_group', ''));
        $savedCount = 0;
        $savedIds   = [];

        DB::beginTransaction();
        try {
            foreach ($inputRows as $row) {
                $lastName = trim((string) ($row['last_name'] ?? ''));
                $firstName = trim((string) ($row['first_name'] ?? ''));

                if ($lastName === '' || $firstName === '') {
                    continue;
                }

                $middleName    = trim((string) ($row['middle_name'] ?? '')) ?: null;
                $courseChoice1 = trim((string) ($row['course_choice_1'] ?? ($row['course_choice'] ?? ''))) ?: null;
                $rawC2         = trim((string) ($row['course_choice_2'] ?? ($row['second_course_choice'] ?? '')));
                $courseChoice2 = ($rawC2 === '' || strcasecmp($rawC2, 'N/A') === 0 || strcasecmp($rawC2, 'None') === 0) ? null : $rawC2;

                $sex          = trim((string) ($row['sex'] ?? '')) ?: null;
                $specialGroup = trim((string) ($row['special_group'] ?? ($row['4ps_osy_ip_pwd_sp'] ?? ''))) ?: null;
                $cmfl         = trim((string) ($row['cmfl'] ?? '')) ?: null;
                $gwa          = (isset($row['gwa']) && is_numeric($row['gwa'])) ? (float) $row['gwa'] : null;

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

                $applicant->last_name   = $lastName;
                $applicant->first_name  = $firstName;
                $applicant->middle_name = $middleName;
                $applicant->sex         = $sex;
                $applicant->special_group = $specialGroup;
                $applicant->cmfl        = $cmfl;
                $applicant->gwa         = $gwa;

                // ── Schema-aware course-choice writes ──────────────────────────
                // Only set alias columns when they exist in the DB, so this never
                // crashes on a remote database (Railway) missing the new columns.
                static $schemaColumns = null;
                if ($schemaColumns === null) {
                    $schemaColumns = \Illuminate\Support\Facades\Schema::getColumnListing('admission_applicants');
                }

                $applicant->setAttribute('course_choice', $courseChoice1);

                if (in_array('course_choice_1', $schemaColumns, true)) {
                    $applicant->setAttribute('course_choice_1', $courseChoice1);
                }
                if (in_array('second_course_choice', $schemaColumns, true)) {
                    $applicant->setAttribute('second_course_choice', $courseChoice2);
                }
                if (in_array('course_choice_2', $schemaColumns, true)) {
                    $applicant->setAttribute('course_choice_2', $courseChoice2);
                }

                if (isset($row['exam_score']) && is_numeric($row['exam_score'])) {
                    $applicant->exam_score = (float) $row['exam_score'];
                }
                if (isset($row['interview_score']) && is_numeric($row['interview_score'])) {
                    $applicant->interview_score = (float) $row['interview_score'];
                }

                if ($batchGroup !== '' && $batchGroup !== '__all__') {
                    $applicant->batch_group = $batchGroup;
                }

                $applicant->save();
                $savedCount++;
                $savedIds[] = $applicant->id;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error saving encoding sheet rows: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to save encoded rows: ' . $e->getMessage(),
                    'errors'  => [$e->getMessage()],
                ], 422);
            }

            return back()->withInput()->with('error', 'Unable to save encoded rows: ' . $e->getMessage());
        }

        if ($savedCount > 0) {
            try {
                $scoring->evaluate($cycle);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Scoring evaluation warning on cycle: ' . $e->getMessage());
            }
        }

        $message = "Successfully saved {$savedCount} row(s) to the Masterlist Encoding Sheet.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'     => true,
                'message'     => $message,
                'saved_count' => $savedCount,
                'saved_ids'   => $savedIds,
            ]);
        }

        return redirect()->route('admin.admission.encoding-sheet', ['batch_group' => $batchGroup, 'cycle_id' => $cycle->id])
            ->with('success', $message);
    }

    public function saveApplicant(Request $request, ?AdmissionApplicant $applicant = null, AdmissionScoringService $scoring)
    {
        $cycleId = $request->input('cycle_id');
        $cycle = $cycleId ? AdmissionCycle::find($cycleId) : ($applicant ? $applicant->cycle : $this->active());

        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        abort_if($cycle->isCompleted(), 422, 'This admission cycle is archived/completed and locked from edits.');

        if ($applicant) abort_unless($applicant->admission_cycle_id === $cycle->id, 404);

        $data = $request->validate([
            'application_number' => ['required', 'string', 'max:80', Rule::unique('admission_applicants')->ignore($applicant?->id)],
            'student_id' => 'nullable|string|max:80',
            'first_name' => 'required|string|max:120',
            'middle_name' => 'nullable|string|max:120',
            'last_name' => 'required|string|max:120',
            'course_choice' => ['nullable', CourseCatalog::rule()],
            'course_choice_1' => ['nullable', CourseCatalog::rule()],
            'second_course_choice' => ['nullable', CourseCatalog::rule()],
            'course_choice_2' => ['nullable', CourseCatalog::rule()],
            'sex' => 'nullable|string|max:20',
            'special_group' => ['nullable', 'string', Rule::in(['N/A', '4Ps', 'OSY', 'IP', 'PWD', 'SP'])],
            'cmfl' => ['nullable', 'string', Rule::in(['N/A', '10,000 below', '10,001 to 20,000', '20,001 to 30,000', '30,001 to 50,000', '50,001 and above'])],
            'gwa' => 'nullable|numeric|between:75,100',
            'interview_score' => 'nullable|numeric|between:0,100',
        ]);

        // Schema-aware alias sync: only include alias columns that exist in the DB.
        $schemaCols = \Illuminate\Support\Facades\Schema::getColumnListing('admission_applicants');

        // Sync course_choice <-> course_choice_1
        if (empty($data['course_choice']) && !empty($data['course_choice_1'])) {
            $data['course_choice'] = $data['course_choice_1'];
        } elseif (!empty($data['course_choice'])) {
            if (in_array('course_choice_1', $schemaCols, true)) {
                $data['course_choice_1'] = $data['course_choice'];
            }
        }
        // Remove alias keys that don't exist in this DB to avoid Unknown column errors
        if (!in_array('course_choice_1', $schemaCols, true)) {
            unset($data['course_choice_1']);
        }

        // Sync second_course_choice <-> course_choice_2
        if (empty($data['second_course_choice']) && !empty($data['course_choice_2'])) {
            if (in_array('second_course_choice', $schemaCols, true)) {
                $data['second_course_choice'] = $data['course_choice_2'];
            }
        } elseif (!empty($data['second_course_choice'])) {
            if (in_array('course_choice_2', $schemaCols, true)) {
                $data['course_choice_2'] = $data['second_course_choice'];
            }
        }
        if (!in_array('second_course_choice', $schemaCols, true)) {
            unset($data['second_course_choice']);
        }
        if (!in_array('course_choice_2', $schemaCols, true)) {
            unset($data['course_choice_2']);
        }

        $record = $applicant ?? new AdmissionApplicant();
        $record->fill($data);
        $record->admission_cycle_id = $cycle->id;
        $record->save();

        // Persist the applicant's total before recalculating the masterlist.
        // This prevents a legacy/corrupt row elsewhere in the cycle from
        // turning a valid interview-score save into an upstream 502.
        $scoring->refreshApplicantTotal($record);

        try {
            $scoring->evaluate($cycle);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.admission.masterlist', ['cycle_id' => $cycle->id])
                ->with('warning', 'Applicant saved and total score updated. The masterlist ranking refresh could not finish; please review the server log.');
        }

        return redirect()->route('admin.admission.masterlist', ['cycle_id' => $cycle->id])->with('success', 'Applicant saved.');
    }

    /** Save an interview score directly from the masterlist row. */
    public function saveInterviewScore(Request $request, AdmissionApplicant $applicant, AdmissionScoringService $scoring)
    {
        $cycle = $applicant->cycle;
        abort_unless($cycle, 404);
        abort_if($cycle->isCompleted(), 422, 'This admission cycle is archived/completed and locked from edits.');

        $data = $request->validate([
            'interview_score' => ['required', 'numeric', 'between:0,100'],
        ]);

        $applicant->forceFill(['interview_score' => $data['interview_score']])->save();
        $scoring->refreshApplicantTotal($applicant);

        $warning = null;
        try {
            $scoring->evaluate($cycle);
        } catch (\Throwable $exception) {
            // The score and total were already saved. A separate ranking
            // issue must not leave the administrator at a 502 page.
            report($exception);
            $warning = 'Score and total were saved, but the complete masterlist ranking refresh could not finish.';
        }

        $updated = $applicant->fresh(['cycle', 'admissionSession']);
        $evaluation = $updated->qualification_evaluation;

        return response()->json([
            'saved' => true,
            'warning' => $warning,
            'interview_score' => (float) $updated->interview_score,
            'interview_weight' => (float) $cycle->interview_weight,
            'total_score' => $updated->calculated_total,
            'qualification_status' => $updated->qualification_status,
            'remarks' => $evaluation['remarks'],
            'remarks_badge' => $evaluation['badge'] ?? 'bg-secondary',
        ]);
    }

    public function import(Request $request, AdmissionScoringService $scoring, \App\Services\AdmissionSpreadsheetReader $reader)
    {
        $cycleId = $request->input('cycle_id');
        $cycle = $cycleId ? AdmissionCycle::find($cycleId) : $this->active();

        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        abort_if($cycle->isCompleted(), 422, 'Cannot import applicants into an archived/completed cycle.');

        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:5120']);
        $batchGroup = trim((string) $request->input('batch_group', ''));

        try {
            $importer = new \App\Imports\AdmissionApplicantImport($reader, $scoring);
            $result   = $importer->import($request->file('file'), $cycle, $batchGroup);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $msg = "Imported {$result['imported']} applicant(s).";
        return back()->with('success', $msg)->with('import_errors', $result['errors']);
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

    public function paper(Request $request, AdmissionApplicant $applicant)
    {
        $cycle = $applicant->cycle ?? $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();
        $totalItems = (int) ($cycle->total_items ?: 80);
        $isPdf = $request->query('format') === 'pdf';
        $psuLogoUrl = asset('images/psu-logo.png');

        if (!$isPdf) {
            return view('admin.admission.paper', compact('applicant', 'totalItems', 'isPdf', 'psuLogoUrl'));
        }

        // Dompdf can embed JPEGs without the optional PHP GD extension.
        $logoPath = public_path('images/psu-logo.jpg');
        if (is_file($logoPath)) {
            $psuLogoUrl = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        try {
            $html = view('admin.admission.paper', compact('applicant', 'totalItems', 'isPdf', 'psuLogoUrl'))->render();
            $filename = 'PSU-CAT-Answer-Sheet-'.Str::slug($applicant->application_number ?? 'applicant').'.pdf';

            return response($this->pdf($html))
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Admission answer sheet PDF failed', ['exception' => $e]);

            return redirect()->route('admin.exports.index')->with('error', 'Unable to generate the document. Please contact the system administrator or try another format.');
        }
    }

    public function scanner()
    {
        $cycle = $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();
        $totalItems = $cycle->total_items ?: 80;
        return view('admin.admission.scanner', compact('cycle', 'totalItems'));
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
        $cycle = $applicant->cycle;
        if (!$cycle) return $this->gatekeeperRedirect();
        abort_if($cycle->isCompleted(), 422, 'Cannot encode answers on an archived cycle.');
        $totalItems = $cycle->total_items ?: 80;
        return view('admin.admission.encode', compact('applicant', 'totalItems'));
    }

    public function submitPaper(Request $request, AdmissionApplicant $applicant, AdmissionScoringService $scoring)
    {
        $cycle = $applicant->cycle;
        if (!$cycle) return $this->gatekeeperRedirect();
        abort_if($cycle->isCompleted(), 422, 'Cannot submit answers on an archived cycle.');
        $totalItems = $cycle->total_items ?: 80;
        $data = $request->validate([
            'answers' => 'required|array:' . implode(',', range(1, $totalItems)) . '|size:' . $totalItems,
            'answers.*' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
        ]);
        $scoring->submit($applicant, $data['answers']);
        return redirect()->route('admin.admission.masterlist', ['cycle_id' => $cycle->id])->with('success', 'Paper answers scored.');
    }

    public function report(Request $request)
    {
        return app(DocumentExportController::class)->legacyReport($request, 'admission');
    }

    private function pdf(string $html): string
    {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        return $pdf->output();
    }

    public function assignSessionRange(Request $request)
    {
        $cycleId = $request->input('cycle_id');
        $cycle = $cycleId ? AdmissionCycle::find($cycleId) : $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();

        abort_if($cycle->isCompleted(), 422, 'Cannot assign sessions on an archived/completed cycle.');

        $data = $request->validate([
            'session_label' => 'required|string|max:120',
            'start_number'  => 'required|integer|min:1',
            'end_number'    => 'required|integer|gte:start_number',
            'batch_group'   => 'nullable|string|max:100',
            'course'        => 'nullable|string|max:30',
        ]);

        $query = $cycle->applicants();
        if (!empty($data['batch_group']) && $data['batch_group'] !== '__all__') {
            $query->where('batch_group', $data['batch_group']);
        }
        if (!empty($data['course'])) {
            $query->where('course_choice', $data['course']);
        }

        $query->orderBy('course_choice')->orderBy('last_name')->orderBy('first_name');

        $offset = $data['start_number'] - 1;
        $limit = ($data['end_number'] - $data['start_number']) + 1;

        $targetApplicants = $query->skip($offset)->take($limit)->get();

        if ($targetApplicants->isEmpty()) {
            return back()->with('error', 'No applicants found in the specified numerical range.');
        }

        $sessionLabel = trim($data['session_label']);
        foreach ($targetApplicants as $app) {
            $app->update(['session_label' => $sessionLabel]);
        }

        return back()->with('success', "Successfully assigned {$targetApplicants->count()} applicant(s) (Range: {$data['start_number']} to {$data['end_number']}) to session '{$sessionLabel}'.");
    }

    public function certificate(Request $request, AdmissionApplicant $applicant)
    {
        $cycle = $applicant->cycle ?? AdmissionCycle::find($applicant->admission_cycle_id) ?? AdmissionCycle::active();

        $purpose = trim((string) $request->input('purpose', 'SCHOLARSHIP'));
        if ($purpose === '') {
            $purpose = 'SCHOLARSHIP';
        }

        $defaultRequestor = ($applicant->sex === 'Female' ? 'MS. ' : 'MR. ') . mb_strtoupper($applicant->last_name);
        $requestorName = trim((string) $request->input('requestor_name', $defaultRequestor));
        if ($requestorName === '') {
            $requestorName = $defaultRequestor;
        }

        $orNumber = trim((string) $request->input('or_number', ''));
        $orDate   = trim((string) $request->input('or_date', ''));

        $salutation = match(strtolower((string) $applicant->sex)) {
            'female' => 'MS.',
            'male'   => 'MR.',
            default  => '',
        };

        $rawIssuedDate = $request->input('issued_date');
        try {
            $dateObj = $rawIssuedDate ? \Illuminate\Support\Carbon::parse($rawIssuedDate) : now();
        } catch (\Throwable) {
            $dateObj = now();
        }

        $dayNum = (int) $dateObj->format('j');
        $suffix = match(true) {
            $dayNum === 1 || $dayNum === 21 || $dayNum === 31 => 'st',
            $dayNum === 2 || $dayNum === 22                   => 'nd',
            $dayNum === 3 || $dayNum === 23                   => 'rd',
            default                                           => 'th',
        };
        $issuedDay   = $dayNum . $suffix;
        $issuedMonth = $dateObj->format('F');
        $issuedYear  = $dateObj->format('Y');
        $dateIssued  = "Issued this {$issuedDay} day of {$issuedMonth}, {$issuedYear}.";

        $remarks = $applicant->certificate_remarks;

        if ($request->query('format') === 'pdf' || $request->input('format') === 'pdf') {
            try {
                $options = new \Dompdf\Options();
                $options->set('isRemoteEnabled', false);
                $options->set('isHtml5ParserEnabled', true);
                $dompdf = new \Dompdf\Dompdf($options);

                $html = view('admin.admission.certificate', compact(
                    'applicant',
                    'cycle',
                    'purpose',
                    'requestorName',
                    'dateIssued',
                    'issuedDay',
                    'issuedMonth',
                    'issuedYear',
                    'salutation',
                    'orNumber',
                    'orDate',
                    'remarks'
                ))->render();

                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('letter', 'portrait');
                $dompdf->render();

                $filename = 'PSU-CAT-Certificate-' . \Illuminate\Support\Str::slug($applicant->application_number ?? ('applicant-' . $applicant->id)) . '.pdf';

                return response($dompdf->output(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Admission certificate PDF generation failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);

                return back()->with('error', 'Unable to generate PDF certificate: ' . $e->getMessage());
            }
        }

        return view('admin.admission.certificate', compact(
            'applicant',
            'cycle',
            'purpose',
            'requestorName',
            'dateIssued',
            'issuedDay',
            'issuedMonth',
            'issuedYear',
            'salutation',
            'orNumber',
            'orDate',
            'remarks'
        ));
    }

    private function active(): ?AdmissionCycle
    {
        return AdmissionCycle::active();
    }

    private function gatekeeperRedirect()
    {
        return redirect()->route('admin.admission.index')
            ->with('warning', 'Please select or initialize an active Admission Cycle (e.g., S.Y. 2026 – 2027) before accessing admission records.');
    }
}
