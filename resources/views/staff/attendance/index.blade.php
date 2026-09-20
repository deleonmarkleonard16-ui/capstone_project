@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Attendance Logs</h1>
            <p class="text-muted mb-0">{{ $session->title }} | {{ $session->exam_date->format('M d, Y') }} | {{ $session->start_time }} - {{ $session->end_time }}</p>
        </div>
        <a href="{{ route(auth()->user()->role.'.sessions.monitoring.show', $session) }}" class="btn btn-outline-secondary">Back to Monitoring</a>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>Applicant</th><th>Application Number</th><th>Verified Name</th><th>Gender</th><th>Scanned At</th><th>IP Address</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ $log->applicant->full_name }}</td>
                                <td>{{ $log->verified_application_number }}</td>
                                <td>{{ $log->verified_name }}</td>
                                <td>{{ $log->verified_gender }}</td>
                                <td>{{ $log->scanned_at->format('M d, Y h:i:s A') }}</td>
                                <td>{{ $log->ip_address ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No attendance logs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
