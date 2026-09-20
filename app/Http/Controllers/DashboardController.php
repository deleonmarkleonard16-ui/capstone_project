<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\AnswerSheet;
use App\Models\SessionApplicant;
use App\Models\TestSession;
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
                'sessions' => TestSession::count(),
                'present_today' => SessionApplicant::whereDate('scanned_at', today())->count(),
                'submitted_today' => AnswerSheet::whereDate('submitted_at', today())->count(),
            ],
            'sessions' => TestSession::latest('exam_date')->latest()->take(5)->get(),
        ]);
    }
}
