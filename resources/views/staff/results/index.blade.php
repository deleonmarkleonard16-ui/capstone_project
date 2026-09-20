@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Exam Results</h1>
            <p class="text-muted mb-0">{{ $session->title }} | Passed: {{ $passedCount }} | Submitted: {{ $results->count() }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route(auth()->user()->role.'.sessions.answer-key.edit', $session) }}" class="btn btn-outline-primary">Edit Answer Key</a>
            <a href="{{ route(auth()->user()->role.'.sessions.monitoring.show', $session) }}" class="btn btn-outline-secondary">Back to Monitoring</a>
        </div>
    </div>

    @if (! $session->answerKey)
        <div class="alert alert-warning">
            No answer key has been saved for this session yet. Add the key first to compute pass/fail results.
        </div>
    @endif

    <div class="card page-card">
        <div class="card-body">
            <form method="GET" action="{{ route(auth()->user()->role.'.sessions.results.index', $session) }}" class="row g-3 align-items-end mb-4">
                <div class="col-md-6">
                    <label for="search" class="form-label">Search Applicant</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="form-control"
                        value="{{ $search }}"
                        placeholder="Search by name, application number, or email"
                    >
                </div>
                <div class="col-md-4">
                    <label for="sort" class="form-label">Sort By</label>
                    <select id="sort" name="sort" class="form-select">
                        <option value="score_desc" @selected($sort === 'score_desc')>Highest Score</option>
                        <option value="score_asc" @selected($sort === 'score_asc')>Lowest Score</option>
                        <option value="name_asc" @selected($sort === 'name_asc')>Name A-Z</option>
                        <option value="name_desc" @selected($sort === 'name_desc')>Name Z-A</option>
                        <option value="submitted_desc" @selected($sort === 'submitted_desc')>Latest Submission</option>
                        <option value="submitted_asc" @selected($sort === 'submitted_asc')>Earliest Submission</option>
                        <option value="status_asc" @selected($sort === 'status_asc')>Passed First</option>
                        <option value="status_desc" @selected($sort === 'status_desc')>Did Not Pass First</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                    <a href="{{ route(auth()->user()->role.'.sessions.results.index', $session) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>Applicant</th><th>Application Number</th><th>Score</th><th>Passing Score</th><th>Status</th><th>Submitted At</th><th class="text-end">Review</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($results as $result)
                            <tr>
                                <td>{{ $result['sheet']->applicant->full_name }}</td>
                                <td>{{ $result['sheet']->applicant->application_number }}</td>
                                <td>{{ $result['score'] }}/80</td>
                                <td>{{ $session->answerKey?->passing_score ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $result['passed'] ? 'text-bg-success' : 'text-bg-danger' }}">
                                        {{ $result['passed'] ? 'Passed' : 'Did Not Pass' }}
                                    </span>
                                </td>
                                <td>{{ optional($result['sheet']->submitted_at)->format('M d, Y h:i:s A') ?: '-' }}</td>
                                <td class="text-end">
                                    @if ($session->isFinished())
                                        <a href="{{ route(auth()->user()->role.'.sessions.results.show', [$session, $result['sheet']]) }}" class="btn btn-sm btn-outline-primary">View Details</a>
                                    @else
                                        <span class="text-muted small">Available after test ends</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No results matched your search or filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
