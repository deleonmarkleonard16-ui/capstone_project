@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Session Applicant Assignment</h1>
            <p class="text-muted mb-0">{{ $session->title }} | {{ $session->exam_date->format('M d, Y') }} | Room {{ $session->room }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route(auth()->user()->role.'.sessions.index') }}" class="btn btn-outline-secondary">Back to Sessions</a>
            <form method="POST" action="{{ route(auth()->user()->role.'.sessions.regenerate-qr', $session) }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary">Regenerate Session QR</button>
            </form>
            <a href="{{ route(auth()->user()->role.'.sessions.monitoring.show', $session) }}" class="btn btn-primary">Open Monitoring</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card page-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Applicant Tools</h2>
                        <a href="{{ route(auth()->user()->role.'.applicants.create') }}" class="btn btn-primary btn-sm">Add Applicant</a>
                    </div>
                    <p class="text-muted">You can create a new applicant record or import a CSV file, then return here to assign them to this session.</p>
                    <div class="small bg-light border rounded p-3 mb-3">
                        application_number, first_name, middle_name, last_name, gender, email, contact_number, status
                    </div>
                    <form method="POST" action="{{ route(auth()->user()->role.'.applicants.import') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ route(auth()->user()->role.'.sessions.assignments.index', $session) }}">
                        <div class="mb-3">
                            <label class="form-label">Import Applicants From Excel CSV</label>
                            <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">Import CSV to Applicant Master List</button>
                    </form>
                </div>
            </div>

            <div class="card page-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Assign Applicants</h2>
                    <form method="POST" action="{{ route(auth()->user()->role.'.sessions.assignments.store', $session) }}">
                        @csrf
                        <div class="mb-3" style="max-height: 360px; overflow-y: auto;">
                            @forelse ($availableApplicants as $applicant)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="applicant_ids[]" value="{{ $applicant->id }}" id="applicant-{{ $applicant->id }}">
                                    <label class="form-check-label" for="applicant-{{ $applicant->id }}">
                                        {{ $applicant->full_name }}<br>
                                        <small class="text-muted">{{ $applicant->application_number }}</small>
                                    </label>
                                </div>
                            @empty
                                <p class="text-muted mb-0">All applicants are already assigned to this session.</p>
                            @endforelse
                        </div>
                        <button type="submit" class="btn btn-primary w-100" @disabled($availableApplicants->isEmpty())>Assign Selected Applicants</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card page-card">
                <div class="card-body">
                    <div class="row g-4 mb-4 align-items-center">
                        <div class="col-md-5 text-center">
                            <img src="{{ $session->qrImageUrl() }}" alt="Session QR code" class="img-thumbnail" style="width: 220px;">
                        </div>
                        <div class="col-md-7">
                            <h2 class="h5 mb-3">Shared Session QR Code</h2>
                            <p class="mb-2">This single QR code is used by every applicant assigned to this exam schedule.</p>
                            <p class="text-muted mb-3">Applicants scan the same QR during the scheduled time, then verify their identity to record attendance.</p>
                            <a href="{{ $session->checkinUrl() }}" target="_blank" class="btn btn-outline-primary btn-sm">Open Session Check-In Link</a>
                            <div class="small text-muted mt-3">
                                QR public URL: {{ config('app.qr_public_url') }}
                            </div>
                            <div class="small text-muted mt-1">
                                For mobile data access, set <code>QR_PUBLIC_URL</code> to your public tunnel or deployed domain, then regenerate the session QR.
                            </div>
                        </div>
                    </div>

                    <h2 class="h5 mb-3">Assigned Applicants</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr><th>Applicant</th><th>Attendance</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($session->sessionApplicants as $assignment)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $assignment->applicant->full_name }}</div>
                                            <div class="text-muted small">{{ $assignment->applicant->application_number }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $assignment->is_present ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $assignment->is_present ? 'Present' : 'Pending' }}</span>
                                            <div class="small text-muted mt-1">{{ $assignment->scanned_at ? $assignment->scanned_at->format('M d, Y h:i A') : 'Not yet scanned' }}</div>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route(auth()->user()->role.'.sessions.assignments.destroy', [$session, $assignment]) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">No applicants assigned yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
