@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Admission Test</h1>
            <p class="text-muted mb-0">Manage applicant records and open the session setup used for admission testing.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route(auth()->user()->role.'.sessions.index') }}" class="btn btn-outline-primary">Open Test Sessions</a>
            <a href="{{ route(auth()->user()->role.'.applicants.create') }}" class="btn btn-primary">Add Applicant</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card page-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-2">Applicant Master List</h2>
                    <p class="text-muted mb-0">Store the official applicant records used during session check-in and identity verification.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card page-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-2">Test Sessions</h2>
                    <p class="text-muted mb-3">Create schedules, assign applicants, generate the session QR code, and monitor live exam progress.</p>
                    <a href="{{ route(auth()->user()->role.'.sessions.index') }}" class="btn btn-outline-primary">Go to Test Sessions</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card page-card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Import Applicants From Excel CSV</h2>
                    <p class="text-muted">Upload a CSV file exported from Excel with these columns:</p>
                    <div class="small bg-light border rounded p-3 mb-3">
                        application_number, first_name, middle_name, last_name, gender, email, contact_number, status
                    </div>
                    <form method="POST" action="{{ route(auth()->user()->role.'.applicants.import') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                        <div class="mb-3">
                            <label class="form-label">CSV File</label>
                            <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Import Applicants</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card page-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <h2 class="h5 mb-0">Applicant Master List</h2>
                        <span class="text-muted small">{{ $applicants->total() }} record(s)</span>
                    </div>
                    <form method="GET" action="{{ route(auth()->user()->role.'.applicants.index') }}" class="row g-2 mb-3">
                        <div class="col-md-9">
                            <input type="text" name="search" class="form-control" placeholder="Search by application number, name, email, or contact number" value="{{ $search }}">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-outline-primary">Search</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr><th>Application No.</th><th>Name</th><th>Gender</th><th>Status</th><th>Email</th><th class="text-end">Action</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($applicants as $applicant)
                                    <tr>
                                        <td>{{ $applicant->application_number }}</td>
                                        <td>{{ $applicant->full_name }}</td>
                                        <td>{{ $applicant->gender }}</td>
                                        <td><span class="badge text-bg-info">{{ ucfirst($applicant->status) }}</span></td>
                                        <td>{{ $applicant->email ?: '-' }}</td>
                                        <td class="text-end"><a href="{{ route(auth()->user()->role.'.applicants.edit', $applicant) }}" class="btn btn-outline-primary btn-sm">Edit</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No applicants found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $applicants->links() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
