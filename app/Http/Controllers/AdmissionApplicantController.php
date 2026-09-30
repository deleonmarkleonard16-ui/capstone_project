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

        DB::transaction(function () use ($inputRows, $cycle, $batchGroup, &$savedCount, &$savedIds) {
            foreach ($inputRows as $row) {
                $lastName  = trim((string) ($row['last_name'] ?? ''));
                $firstName = trim((string) ($row['first_name'] ?? ''));

                // Skip rows where name is not provided
                if ($lastName === '' || $firstName === '') {
                    continue;
                }

                $middleName    = trim((string) ($row['middle_name'] ?? '')) ?: null;
                $courseChoice1 = trim((string) ($row['course_choice_1'] ?? ($row['course_choice'] ?? '')));
                $courseChoice2 = trim((string) ($row['course_choice_2'] ?? ($row['second_course_choice'] ?? '')));
                if ($courseChoice2 === '' || strcasecmp($courseChoice2, 'N/A') === 0 || strcasecmp($courseChoice2, 'None') === 0) {
                    $courseChoice2 = null;
                }

                $sex          = trim((string) ($row['sex'] ?? '')) ?: null;
                $specialGroup = trim((string) ($row['special_group'] ?? ($row['4ps_osy_ip_pwd_sp'] ?? ''))) ?: null;
                $cmfl         = trim((string) ($row['cmfl'] ?? '')) ?: null;
                $gwa          = isset($row['gwa']) && is_numeric($row['gwa']) ? (float) $row['gwa'] : null;

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

                $applicant->last_name            = $lastName;
                $applicant->first_name           = $firstName;
                $applicant->middle_name          = $middleName;
                if ($courseChoice1 !== '') {
                    $applicant->course_choice_1  = $courseChoice1;
                    $applicant->course_choice    = $courseChoice1;
                }
                $applicant->course_choice_2      = $courseChoice2;
                $applicant->second_course_choice = $courseChoice2;
                $applicant->sex                  = $sex;
                $applicant->special_group        = $specialGroup;
                $applicant->cmfl                 = $cmfl;
                $applicant->gwa                  = $gwa;

                if ($batchGroup !== '' && $batchGroup !== '__all__') {
                    $applicant->batch_group = $batchGroup;
                }

                $applicant->save();
                $savedCount++;
                $savedIds[] = $applicant->id;
            }
        });

        if ($savedCount > 0) {
            $scoring->evaluate($cycle);
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
