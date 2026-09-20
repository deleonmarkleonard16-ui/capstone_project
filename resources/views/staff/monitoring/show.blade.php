@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Live Monitoring</h1>
            <p class="text-muted mb-0">{{ $session->title }} | {{ $session->exam_date->format('M d, Y') }} | {{ $session->start_time }} - {{ $session->end_time }} | Room {{ $session->room }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route(auth()->user()->role.'.sessions.assignments.index', $session) }}" class="btn btn-outline-secondary">Manage Assignments</a>
            <a href="{{ route(auth()->user()->role.'.sessions.attendance.index', $session) }}" class="btn btn-outline-primary">Attendance Logs</a>
            <a href="{{ route(auth()->user()->role.'.sessions.answer-key.edit', $session) }}" class="btn btn-outline-primary">Answer Key</a>
            <a href="{{ route(auth()->user()->role.'.sessions.results.index', $session) }}" class="btn btn-outline-success">Passed Applicants</a>
            <form method="POST" action="{{ route(auth()->user()->role.'.sessions.monitoring.start', $session) }}">
                @csrf
                <button type="submit" class="btn btn-danger" @disabled(! $session->canBeStarted())>Start Test</button>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-2"><div class="card stat-card h-100"><div class="card-body"><p class="text-muted mb-2">Total Applicants</p><h2 class="mb-0" data-stat="total_applicants">{{ $stats['total_applicants'] }}</h2></div></div></div>
        <div class="col-md-2"><div class="card stat-card h-100"><div class="card-body"><p class="text-muted mb-2">Checked In</p><h2 class="mb-0" data-stat="checked_in">{{ $stats['checked_in'] }}</h2></div></div></div>
        <div class="col-md-2"><div class="card stat-card h-100"><div class="card-body"><p class="text-muted mb-2">Absent</p><h2 class="mb-0" data-stat="absent">{{ $stats['absent'] }}</h2></div></div></div>
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><p class="text-muted mb-2">Submitted</p><h2 class="mb-0" data-stat="submitted">{{ $stats['submitted'] }}</h2></div></div></div>
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><p class="text-muted mb-2">Status</p><h2 class="h4 mb-0 text-capitalize" data-stat="status">{{ str_replace('_', ' ', $stats['status']) }}</h2></div></div></div>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Attendance Tracking</h2>
                <span class="text-muted small">Auto-refreshes every 10 seconds</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>Applicant</th><th>Application No.</th><th>Attendance</th><th>Scanned At</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($session->sessionApplicants as $assignment)
                            <tr>
                                <td>{{ $assignment->applicant->full_name }}</td>
                                <td>{{ $assignment->applicant->application_number }}</td>
                                <td><span class="badge {{ $assignment->is_present ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $assignment->is_present ? 'Present' : 'Absent / Pending' }}</span></td>
                                <td>{{ $assignment->scanned_at ? $assignment->scanned_at->format('M d, Y h:i:s A') : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No applicants assigned to this session.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const statsUrl = @json(route(auth()->user()->role.'.sessions.monitoring.stats', $session));

        setInterval(async () => {
            const response = await fetch(statsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) return;
            const stats = await response.json();
            document.querySelector('[data-stat="total_applicants"]').textContent = stats.total_applicants;
            document.querySelector('[data-stat="checked_in"]').textContent = stats.checked_in;
            document.querySelector('[data-stat="absent"]').textContent = stats.absent;
            document.querySelector('[data-stat="submitted"]').textContent = stats.submitted;
            document.querySelector('[data-stat="status"]').textContent = stats.status.replaceAll('_', ' ');
        }, 10000);
    </script>
@endpush
