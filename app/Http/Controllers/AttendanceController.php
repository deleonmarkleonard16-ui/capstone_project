<?php

namespace App\Http\Controllers;

use App\Models\TestSession;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(TestSession $session): View
    {
        return view('staff.attendance.index', [
            'session' => $session,
            'logs' => $session->attendanceLogs()
                ->with('applicant')
                ->latest('scanned_at')
                ->get(),
        ]);
    }
}
