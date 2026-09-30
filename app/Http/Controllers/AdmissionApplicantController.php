<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Services\AdmissionScoringService;
use App\Support\CourseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdmissionApplicantController extends Controller
{
    /**
     * Store / batch save encoded applicant rows from the Masterlist Encoding Sheet.
     * Supports both JSON/AJAX requests and standard form submissions.
     */
    public function storeBatchEncoded(Request $request, AdmissionScoringService $scoring): JsonResponse|RedirectResponse
    {
        $cycleId = $request->input('cycle_id');
        $cycle   = $cycleId ? AdmissionCycle::find($cycleId) : AdmissionCycle::active();

        if (!$cycle) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Active admission cycle not found.'], 422);
            }
            return redirect()->route('admin.admission.index')
                ->with('warning', 'Please select or initialize an active Admission Cycle.');
        }

        if ($cycle->isCompleted()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'This cycle is archived and locked from edits.'], 422);
            }
            return back()->with('error', 'This cycle is archived and locked from edits.');
        }

        $inputRows  = $request->input('rows', []);
        $batchGroup = trim((string) $request->input('batch_group', ''));
        $savedCount = 0;
        $savedIds   = [];

        DB::beginTransaction();
        try {
            foreach ($inputRows as $row) {
                $lastName  = trim((string) ($row['last_name'] ?? ''));
                $firstName = trim((string) ($row['first_name'] ?? ''));

                // Skip rows where name is not provided
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
                $applicant = null;
                if ($id) {
                    $applicant = AdmissionApplicant::where('id', $id)
                        ->where('admission_cycle_id', $cycle->id)
                        ->first();
                }

                if (!$applicant) {
                    $yearPrefix = date('y');
                    $appNum     = "CAT-{$yearPrefix}-" . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
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

                // ── Course choice columns — only assign aliases that exist in the DB ──
                // This prevents "Unknown column" SQL errors on databases where the
                // new alias columns (second_course_choice, course_choice_1/2) haven't
                // been migrated yet (e.g. Railway remote database).
                static $schemaColumns = null;
                if ($schemaColumns === null) {
                    $schemaColumns = \Illuminate\Support\Facades\Schema::getColumnListing('admission_applicants');
                }

                // Primary (always-present) column: course_choice
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
            \Illuminate\Support\Facades\Log::error('Error saving encoded rows: ' . $e->getMessage(), [
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

        return redirect()->route('admin.admission.encoding-sheet', [
            'batch_group' => $batchGroup,
            'cycle_id'    => $cycle->id,
        ])->with('success', $message);
    }
}
