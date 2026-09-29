<?php

namespace App\Http\Controllers;

use App\Models\AdmissionSession;
use App\Models\Applicant;
use App\Models\AnswerSheet;
use App\Models\SessionApplicant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        if (auth()->user()->role !== 'admin') {
            return view('staff.dashboard');
        }

        return view('staff.dashboard', [
            'stats' => [
                'applicants' => Applicant::count(),
                'sessions' => AdmissionSession::count(),
                'present_today' => SessionApplicant::whereDate('scanned_at', today())->count(),
                'submitted_today' => AnswerSheet::whereDate('submitted_at', today())->count(),
            ],
            'sessions' => AdmissionSession::with('cycle')->orderByDesc('start_time')->take(5)->get(),
        ]);
    }
}
