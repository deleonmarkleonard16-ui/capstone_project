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
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><p class="text-muted mb-2">Applicants</p><h2 class="mb-0">{{ $stats['applicants'] }}</h2></div></div></div>
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><p class="text-muted mb-2">Test Sessions</p><h2 class="mb-0">{{ $stats['sessions'] }}</h2></div></div></div>
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><p class="text-muted mb-2">Checked In Today</p><h2 class="mb-0">{{ $stats['present_today'] }}</h2></div></div></div>
        <div class="col-md-3"><div class="card stat-card h-100"><div class="card-body"><p class="text-muted mb-2">Submitted Today</p><h2 class="mb-0">{{ $stats['submitted_today'] }}</h2></div></div></div>
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
            <div class="row g-3">
                @foreach(['psychological'=>['Psychological Assessment','psychological.index'],'personality'=>['Personality Test','personality.index'],'career'=>['Career Test','career.index'],'good-moral'=>['Good Moral','good-moral'],'exit-form'=>['Exit Form','exit-form']] as $module)
                    <div class="col-md-4"><a class="d-block border rounded-4 p-4 h-100 bg-light text-decoration-none" href="{{ route(auth()->user()->role.'.'.$module[1]) }}"><strong>{{ $module[0] }}</strong><span class="d-block text-muted small mt-2">Open request queue</span></a></div>
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
