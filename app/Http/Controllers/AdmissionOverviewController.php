<?php

namespace App\Http\Controllers;

use App\Models\AdmissionCycle;
use Illuminate\Http\Request;

class AdmissionOverviewController extends Controller
{
    public function analytics(Request $request)
    {
        $cycles = AdmissionCycle::withCount('applicants')->latest('id')->get();
        $cycle = $request->filled('cycle_id')
            ? AdmissionCycle::findOrFail($request->integer('cycle_id'))
            : ($cycles->firstWhere('is_active', true) ?? $cycles->first());
        $statuses = $cycle ? $cycle->applicants()->selectRaw('qualification_status, COUNT(*) as total')
            ->groupBy('qualification_status')->pluck('total', 'qualification_status') : collect();

        $courseStats = $cycle ? $cycle->applicants()
            ->selectRaw('course_choice, COUNT(*) as total, SUM(CASE WHEN qualification_status = "Qualified" THEN 1 ELSE 0 END) as qualified_count')
            ->groupBy('course_choice')
            ->orderBy('course_choice')
            ->get() : collect();

        return view('admin.admission.analytics', compact('cycles', 'cycle', 'statuses', 'courseStats'));
    }

    public function archive()
    {
        $cycles = AdmissionCycle::where('is_archived', true)->withCount('applicants')->latest('id')->paginate(20);

        return view('admin.admission.archive', compact('cycles'));
    }
}
