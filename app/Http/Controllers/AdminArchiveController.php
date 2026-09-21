<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class AdminArchiveController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('year');
        $month = $request->query('month');
        $search = trim((string) $request->query('search', ''));
        $testType = $request->query('test_type');
        $requestType = $request->query('request_type');

        // Section 1: Testing Request Archive
        $testingQuery = ServiceRequest::where('service', 'testing')
            ->where(function ($q) {
                $q->whereNotNull('archived_at')
                  ->orWhereIn('status', ['completed', 'declined', 'cancelled', 'void']);
            });

        if ($year) {
            $testingQuery->whereYear('created_at', $year);
        }
        if ($month) {
            $testingQuery->whereMonth('created_at', $month);
        }
        if ($search !== '') {
            $testingQuery->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }
        if ($testType) {
            $testingQuery->whereJsonContains('tests', $testType);
        }
        $testingRequests = $testingQuery->latest('updated_at')->take(20)->get();

        // Section 2: Good Moral Archive
        $goodMoralQuery = ServiceRequest::where('service', 'good-moral')
            ->where(function ($q) {
                $q->whereNotNull('archived_at')
                  ->orWhereIn('status', ['completed', 'declined', 'cancelled', 'void']);
            });

        if ($year) {
            $goodMoralQuery->whereYear('created_at', $year);
        }
        if ($month) {
            $goodMoralQuery->whereMonth('created_at', $month);
        }
        if ($search !== '') {
            $goodMoralQuery->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }
        $goodMoralRequests = $goodMoralQuery->latest('updated_at')->take(20)->get();

        // Section 3: Exit Form Archive
        $exitFormQuery = ServiceRequest::where('service', 'exit-form')
            ->where(function ($q) {
                $q->whereNotNull('archived_at')
                  ->orWhereIn('status', ['completed', 'declined', 'cancelled', 'void']);
            });

        if ($year) {
            $exitFormQuery->whereYear('created_at', $year);
        }
        if ($month) {
            $exitFormQuery->whereMonth('created_at', $month);
        }
        if ($search !== '') {
            $exitFormQuery->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }
        $exitFormRequests = $exitFormQuery->latest('updated_at')->take(20)->get();

        return view('admin.archive', compact(
            'testingRequests',
            'goodMoralRequests',
            'exitFormRequests',
            'year',
            'month',
            'search',
            'testType',
            'requestType'
        ));
    }
}
