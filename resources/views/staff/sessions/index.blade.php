@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Test Sessions</h1>
            <p class="text-muted mb-0">Create and manage admission testing batches and schedules.</p>
        </div>
        <a href="{{ route(auth()->user()->role.'.sessions.create') }}" class="btn btn-primary">Create Session</a>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>Title</th><th>Exam Date</th><th>Schedule</th><th>Duration</th><th>Room</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr>
                                <td>{{ $session->title }}</td>
                                <td>{{ $session->exam_date->format('M d, Y') }}</td>
                                <td>{{ $session->start_time ? date('h:i A', strtotime($session->start_time)) : '-' }}</td>
                                <td>{{ $session->duration_minutes }} minutes</td>
                                <td>{{ $session->room ?: '-' }}</td>
                                <td><span class="badge text-bg-secondary">{{ ucfirst(str_replace('_', ' ', $session->status)) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route(auth()->user()->role.'.sessions.edit', $session) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                                    <a href="{{ route(auth()->user()->role.'.sessions.assignments.index', $session) }}" class="btn btn-outline-primary btn-sm">Assignments</a>
                                    <a href="{{ route(auth()->user()->role.'.sessions.answer-key.edit', $session) }}" class="btn btn-outline-dark btn-sm">Answer Key</a>
                                    <a href="{{ route(auth()->user()->role.'.sessions.results.index', $session) }}" class="btn btn-outline-success btn-sm">Results</a>
                                    <a href="{{ route(auth()->user()->role.'.sessions.monitoring.show', $session) }}" class="btn btn-primary btn-sm">Monitor</a>
                                    <form method="POST" action="{{ route(auth()->user()->role.'.sessions.destroy', $session) }}" class="d-inline" onsubmit="return confirm('Delete this test session? This will also remove its assignments, answer sheets, attendance logs, and answer key.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No test sessions available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $sessions->links() }}</div>
        </div>
    </div>
@endsection
