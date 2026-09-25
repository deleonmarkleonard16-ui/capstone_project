<?php

namespace App\Http\Controllers;

use App\Models\AdmissionCycle;
use App\Services\AdmissionScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdmissionAnswerKeyController extends Controller
{
    public function update(Request $request, AdmissionScoringService $scoring)
    {
        $cycle = AdmissionCycle::active();
        if (!$cycle) {
            return redirect()->route('admin.admission.index')
                ->with('warning', 'Please select or initialize an active Admission Cycle first.');
        }

        abort_if($cycle->isCompleted(), 422, 'Cannot edit answer key on an archived cycle.');

        $totalItems = (int) ($request->input('total_items') ?: ($cycle->total_items ?: 80));
        $request->merge(['total_items' => $totalItems]);

        $request->validate([
            'total_items' => 'required|integer|min:10|max:200',
            'answers' => 'required|array:' . implode(',', range(1, $totalItems)) . '|size:' . $totalItems,
            'answers.*' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        $data = $request->all();

        DB::transaction(function () use ($cycle, $totalItems, $data) {
            // Update total items on cycle if changed
            if ($cycle->total_items !== $totalItems) {
                $cycle->total_items = $totalItems;
                $cycle->save();
            }

            // Prune any items beyond new total
            DB::table('admission_answer_keys')
                ->where('admission_cycle_id', $cycle->id)
                ->where('item_number', '>', $totalItems)
                ->delete();

            // Mass-assign and sync active answer keys
            foreach ($data['answers'] as $item => $answer) {
                DB::table('admission_answer_keys')->updateOrInsert(
                    ['admission_cycle_id' => $cycle->id, 'item_number' => (int) $item],
                    ['correct_answer' => $answer, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        $scoring->rescoreCycle($cycle);

        return back()->with('success', "Answer key for {$totalItems} items saved successfully and examinee scores updated.");
    }
}
