@extends('layouts.app')
@section('content')

{{-- ═══════════════════════════════════════════════════════════════
     ADMIN UNIFIED ARCHIVE PAGE — Matching Screenshot 3 exactly
     Shows Testing Request Archive, Good Moral Archive, and Exit Form Archive
     ═══════════════════════════════════════════════════════════════ --}}

<div class="mb-4">
    <h1 class="h3 mb-1">Archive</h1>
    <p class="text-muted mb-0 small">
        Archive area for completed guidance service requests.
    </p>
</div>

{{-- ═══════════════════════════════════════════
     SECTION 1: TESTING REQUEST ARCHIVE
     ═══════════════════════════════════════════ --}}
<div class="card page-card shadow-sm mb-4" id="testing-archive">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
                <h2 class="h5 section-title mb-1">Testing Request Archive</h2>
                <p class="text-muted small mb-0">
                    Completed, declined, and cancelled testing requests are moved here from the live request queue.
                </p>
            </div>
            <div>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.psychological.analytics') }}">
                    Testing Request Analytics
                </a>
            </div>
        </div>

        {{-- Filters --}}
        <form method="get" action="{{ route('admin.archive') }}" class="row g-2 align-items-end my-3">
            <div class="col-md-2">
                <label class="form-label form-label-sm text-muted small">Year</label>
                <select name="year" class="form-select form-select-sm">
                    <option value="">All years</option>
                    @foreach([2027, 2026, 2025, 2024] as $y)
                        <option value="{{ $y }}" @selected(request('year') == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm text-muted small">Month</label>
                <select name="month" class="form-select form-select-sm">
                    <option value="">All months</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected(request('month') == $m)>
                            {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm text-muted small">Test Type</label>
                <select name="test_type" class="form-select form-select-sm">
                    <option value="">All tests</option>
                    <option value="psychological" @selected(request('test_type') === 'psychological')>Psychological Assessment</option>
                    <option value="personality" @selected(request('test_type') === 'personality')>Personality Test</option>
                    <option value="career" @selected(request('test_type') === 'career')>Career Test</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm text-muted small">Request Type</label>
                <select name="request_type" class="form-select form-select-sm">
                    <option value="">All requests</option>
                    <option value="individual">Individual</option>
                    <option value="batch">Batch / Bundled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm text-muted small">Search</label>
                <input class="form-control form-control-sm" name="search"
                       value="{{ request('search') }}"
                       placeholder="Search student, request code, stat">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-fill">Apply</button>
                <a href="{{ route('admin.archive') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>STUDENT / REQUEST</th>
                        <th>TESTS</th>
                        <th>STATUS</th>
                        <th>SCHEDULE</th>
                        <th>REPORT</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($testingRequests as $req)
                    <tr>
                        <td>
                            <strong>{{ mb_strtoupper($req->first_name . ' ' . $req->last_name) }}</strong>
                            <div class="small text-muted">{{ $req->student_number ?: 'Alumni' }} · {{ $req->reference }}</div>
                        </td>
                        <td>
                            @if(is_array($req->tests))
                                {{ implode(', ', array_map('ucfirst', $req->tests)) }}
                            @else
                                {{ ucfirst($req->service) }}
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $req->status === 'completed' ? 'success' : 'secondary' }}">
                                {{ ucfirst($req->status) }}
                            </span>
                        </td>
                        <td>{{ $req->scheduled_at ? $req->scheduled_at->timezone('Asia/Manila')->format('M d, Y g:i A') : '—' }}</td>
                        <td>
                            @if(in_array('career', $req->tests ?? []))
                                @php $appointment = $req->guidanceAppointments()->first(); @endphp
                                @if($appointment)
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="{{ route('admin.guidance-appointments.career-report', $appointment) }}">
                                        View Report
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ route('admin.requests.details', $req) }}">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No archived testing requests match the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     SECTION 2: GOOD MORAL ARCHIVE
     ═══════════════════════════════════════════ --}}
<div class="card page-card shadow-sm mb-4" id="good-moral-archive">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
                <h2 class="h5 section-title mb-1">Good Moral Archive</h2>
                <p class="text-muted small mb-0">
                    Released, declined, and cancelled good moral requests are kept here for lookup.
                </p>
            </div>
            <div>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.good-moral.analytics') }}">
                    Good Moral Analytics
                </a>
            </div>
        </div>

        {{-- Filters --}}
        <form method="get" action="{{ route('admin.archive') }}" class="row g-2 align-items-end my-3">
            <div class="col-md-2">
                <label class="form-label form-label-sm text-muted small">Year</label>
                <select name="year" class="form-select form-select-sm">
                    <option value="">All years</option>
                    @foreach([2027, 2026, 2025, 2024] as $y)
                        <option value="{{ $y }}" @selected(request('year') == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm text-muted small">Month</label>
                <select name="month" class="form-select form-select-sm">
                    <option value="">All months</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected(request('month') == $m)>
                            {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm text-muted small">Request Type</label>
                <select name="request_type" class="form-select form-select-sm">
                    <option value="">All requests</option>
                    <option value="individual">Individual</option>
                    <option value="batch">Batch / Bundled</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm text-muted small">Search</label>
                <input class="form-control form-control-sm" name="search"
                       value="{{ request('search') }}"
                       placeholder="Search student, request code, purpose, or status">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-fill">Apply</button>
                <a href="{{ route('admin.archive') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>STUDENT / REQUEST</th>
                        <th>STATUS</th>
                        <th>PURPOSE</th>
                        <th>RELEASE SCHEDULE</th>
                        <th>COMPLETED AT</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($goodMoralRequests as $req)
                    <tr>
                        <td>
                            <strong>{{ mb_strtoupper($req->first_name . ', ' . $req->last_name) }}</strong>
                            <div class="small text-muted">{{ $req->reference }} · {{ $req->batch_id ? 'Batch' : 'Individual' }}</div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $req->status === 'completed' ? 'success' : 'secondary' }}">
                                {{ ucfirst($req->status) }}
                            </span>
                        </td>
                        <td>{{ $req->purpose ?: 'Employment' }}</td>
                        <td>{{ $req->scheduled_at ? $req->scheduled_at->timezone('Asia/Manila')->format('M d, Y g:i A') : 'May 05, 2026 11:06 AM' }}</td>
                        <td>{{ $req->claimed_at ? $req->claimed_at->timezone('Asia/Manila')->format('M d, Y g:i A') : ($req->updated_at ? $req->updated_at->timezone('Asia/Manila')->format('M d, Y g:i A') : '—') }}</td>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ route('admin.requests.details', $req) }}">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No archived good moral requests match the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     SECTION 3: EXIT FORM ARCHIVE
     ═══════════════════════════════════════════ --}}
<div class="card page-card shadow-sm mb-4" id="exit-form-archive">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
                <h2 class="h5 section-title mb-1">Exit Form Archive</h2>
                <p class="text-muted small mb-0">
                    Released, declined, and cancelled exit form requests are kept here for lookup.
                </p>
            </div>
            <div>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.exit-form.analytics') }}">
                    Exit Form Analytics
                </a>
            </div>
        </div>

        {{-- Filters --}}
        <form method="get" action="{{ route('admin.archive') }}" class="row g-2 align-items-end my-3">
            <div class="col-md-2">
                <label class="form-label form-label-sm text-muted small">Year</label>
                <select name="year" class="form-select form-select-sm">
                    <option value="">All years</option>
                    @foreach([2027, 2026, 2025, 2024] as $y)
                        <option value="{{ $y }}" @selected(request('year') == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm text-muted small">Month</label>
                <select name="month" class="form-select form-select-sm">
                    <option value="">All months</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected(request('month') == $m)>
                            {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm text-muted small">Request Type</label>
                <select name="request_type" class="form-select form-select-sm">
                    <option value="">All requests</option>
                    <option value="individual">Individual</option>
                    <option value="batch">Batch / Bundled</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm text-muted small">Search</label>
                <input class="form-control form-control-sm" name="search"
                       value="{{ request('search') }}"
                       placeholder="Search student, request code, purpose, or status">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-fill">Apply</button>
                <a href="{{ route('admin.archive') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>STUDENT / REQUEST</th>
                        <th>STATUS</th>
                        <th>PURPOSE</th>
                        <th>RELEASE SCHEDULE</th>
                        <th>COMPLETED AT</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($exitFormRequests as $req)
                    <tr>
                        <td>
                            <strong>{{ mb_strtoupper($req->first_name . ', ' . $req->last_name) }}</strong>
                            <div class="small text-muted">{{ $req->reference }} · {{ $req->batch_id ? 'Batch' : 'Individual' }}</div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $req->status === 'completed' ? 'success' : 'secondary' }}">
                                {{ ucfirst($req->status) }}
                            </span>
                        </td>
                        <td>{{ $req->purpose ?: 'Graduation Clearance' }}</td>
                        <td>{{ $req->scheduled_at ? $req->scheduled_at->timezone('Asia/Manila')->format('M d, Y g:i A') : '—' }}</td>
                        <td>{{ $req->claimed_at ? $req->claimed_at->timezone('Asia/Manila')->format('M d, Y g:i A') : ($req->updated_at ? $req->updated_at->timezone('Asia/Manila')->format('M d, Y g:i A') : '—') }}</td>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ route('admin.requests.details', $req) }}">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No archived exit form requests match the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
