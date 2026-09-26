@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ auth()->user()->role === 'admin' ? 'Admin' : 'Staff' }} Dashboard</h1>
            <p class="text-muted mb-0">{{ auth()->user()->role === 'admin' ? 'Overview of applicants, sessions, attendance, and submissions.' : 'Access testing and document requests.' }}</p>
        </div>
        @if (auth()->user()->role === 'admin')
        <a href="{{ route(auth()->user()->role.'.sessions.create') }}" class="btn btn-primary">Create Test Session</a>
        @endif
    </div>

    @include('guidance.security-feed')

    @if (auth()->user()->role === 'admin')
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card h-100 border-start border-4 border-primary">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small fw-semibold text-uppercase mb-1">Applicants</p>
                        <h2 class="mb-0 fw-bold">{{ $stats['applicants'] }}</h2>
                    </div>
                    <div class="stat-icon p-2 rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-people-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100 border-start border-4 border-info">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small fw-semibold text-uppercase mb-1">Test Sessions</p>
                        <h2 class="mb-0 fw-bold">{{ $stats['sessions'] }}</h2>
                    </div>
                    <div class="stat-icon p-2 rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-calendar3 fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100 border-start border-4 border-success">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small fw-semibold text-uppercase mb-1">Checked In Today</p>
                        <h2 class="mb-0 fw-bold">{{ $stats['present_today'] }}</h2>
                    </div>
                    <div class="stat-icon p-2 rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-person-check-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100 border-start border-4 border-warning">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small fw-semibold text-uppercase mb-1">Submitted Today</p>
                        <h2 class="mb-0 fw-bold">{{ $stats['submitted_today'] }}</h2>
                    </div>
                    <div class="stat-icon p-2 rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-file-earmark-check-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card page-card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1 section-title">Guidance Modules</h2>
                    <p class="text-muted mb-0">Testing and document requests for the guidance office.</p>
                </div>
            </div>
            @php
                $moduleMeta = [
                    'psychological' => ['Psychological Assessment', 'psychological.index', 'bi-brain', 'text-primary', 'bg-primary'],
                    'personality'   => ['Personality Test', 'personality.index', 'bi-person-badge', 'text-info', 'bg-info'],
                    'career'        => ['Career Test', 'career.index', 'bi-compass', 'text-success', 'bg-success'],
                    'good-moral'    => ['Good Moral', 'good-moral', 'bi-patch-check-fill', 'text-warning', 'bg-warning'],
                    'exit-form'     => ['Exit Form', 'exit-form', 'bi-door-open', 'text-secondary', 'bg-secondary'],
                ];
            @endphp
            <div class="row g-3">
                @foreach($moduleMeta as $modKey => $meta)
                    <div class="col-md-4">
                        <a class="d-flex align-items-center gap-3 border rounded-4 p-3 h-100 bg-light text-decoration-none shadow-xs" href="{{ route(auth()->user()->role.'.'.$meta[1]) }}">
                            <div class="stat-icon p-2 rounded-circle {{ $meta[4] }} bg-opacity-10 {{ $meta[3] }} d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                <i class="bi {{ $meta[2] }} fs-4"></i>
                            </div>
                            <div>
                                <strong class="text-dark d-block">{{ $meta[0] }}</strong>
                                <span class="text-muted small">Open request queue &rarr;</span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @if (auth()->user()->role === 'admin')
    <div class="card page-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Recent Sessions</h2>
                <a href="{{ route(auth()->user()->role.'.sessions.index') }}" class="btn btn-outline-primary btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>Title</th><th>Exam Date</th><th>Room</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr>
                                <td>{{ $session->title }}</td>
                                <td>{{ $session->exam_date->format('M d, Y') }} {{ $session->start_time }}</td>
                                <td>{{ $session->room }}</td>
                                <td><span class="badge text-bg-secondary">{{ ucfirst(str_replace('_', ' ', $session->status)) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route(auth()->user()->role.'.sessions.assignments.index', $session) }}" class="btn btn-outline-primary btn-sm">Assignments</a>
                                    <a href="{{ route(auth()->user()->role.'.sessions.monitoring.show', $session) }}" class="btn btn-primary btn-sm">Monitor</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No sessions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
@endsection
