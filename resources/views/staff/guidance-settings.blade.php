@extends('layouts.app')
@section('content')

{{-- ══════════════════════════════════════════════════════════════
     ADMIN SETTINGS: DYNAMIC COURSE / PROGRAM MANAGEMENT
     Static single header: [ Code ] | [ Program / Course Title ] | [ Status Toggle ] | [ Actions ]
     ══════════════════════════════════════════════════════════════ --}}

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">System Settings</h1>
        <p class="text-muted mb-0 small">Manage institutional academic programs, active statuses, and guidance staff accounts.</p>
    </div>
    @if(request('tab', 'courses') === 'courses')
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
        <i class="bi bi-plus-lg me-1"></i> Add New Program / Course
    </button>
    @endif
</div>

<div class="module-tabs-wrapper mb-4">
<ul class="nav nav-tabs module-tabs" role="tablist" aria-label="System settings">
    <li class="nav-item flex-shrink-0">
        <a class="nav-link {{ request('tab', 'courses') === 'courses' ? 'active fw-bold' : '' }}"
           href="{{ route(auth()->user()->role.'.settings.index', ['tab' => 'courses']) }}">
            <i class="bi bi-mortarboard me-1"></i><span class="tab-label">Course / Program Management</span>
        </a>
    </li>
    <li class="nav-item flex-shrink-0">
        <a class="nav-link {{ request('tab') === 'staff' ? 'active fw-bold' : '' }}"
           href="{{ route(auth()->user()->role.'.settings.index', ['tab' => 'staff']) }}">
            <i class="bi bi-person-gear me-1"></i><span class="tab-label">Guidance Staff Account Management</span>
        </a>
    </li>
</ul>
</div>

@if(request('tab', 'courses') === 'courses')
{{-- ── COMPACT COURSE / PROGRAM TABLE ── --}}
<div class="card page-card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h6 mb-0 fw-bold">Academic Programs &amp; Admission Choices</h2>
            <div class="small text-muted">
                Inactive programs are hidden from new student registration and import dropdowns, but fully preserved in Archives &amp; Analytics.
            </div>
        </div>
        <span class="badge bg-light text-dark border">{{ $courses->count() }} registered programs</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 140px;">Code</th>
                        <th>Program / Course Title</th>
                        <th class="text-center" style="width: 160px;">Status Toggle</th>
                        <th class="pe-3 text-end" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($courses as $course)
                    <tr class="{{ !$course->is_active ? 'table-light opacity-75' : '' }}">
                        <td class="ps-3 fw-bold font-monospace text-primary">
                            {{ $course->code }}
                        </td>
                        <td>
                            <span class="fw-semibold">{{ $course->name }}</span>
                            @unless($course->is_active)
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-2" style="font-size: 10px;">
                                    Inactive / Archived
                                </span>
                            @endunless
                        </td>
                        <td class="text-center">
                            {{-- Direct toggle switch form --}}
                            <form method="post" action="{{ route(auth()->user()->role.'.settings.course.toggle', $course) }}" class="d-inline">
                                @csrf
                                <div class="form-check form-switch d-inline-block mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="toggle-{{ $course->id }}"
                                           onchange="this.form.submit()"
                                           @checked($course->is_active)>
                                    <label class="form-check-label small fw-semibold" for="toggle-{{ $course->id }}">
                                        {{ $course->is_active ? 'Active' : 'Inactive' }}
                                    </label>
                                </div>
                            </form>
                        </td>
                        <td class="pe-3 text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editCourseModal-{{ $course->id }}">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </button>
                            <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Hard deletion is disabled to protect historical data integrity.">
                                <button type="button" class="btn btn-sm btn-outline-secondary opacity-50" disabled>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </span>
                        </td>
                    </tr>

                    {{-- Edit Course Modal --}}
                    <div class="modal fade" id="editCourseModal-{{ $course->id }}" tabindex="-1" aria-labelledby="editLabel{{ $course->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3 class="modal-title h5" id="editLabel{{ $course->id }}">Edit Program: {{ $course->code }}</h3>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="post" action="{{ route(auth()->user()->role.'.settings.course', $course) }}">
                                    @csrf
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold" for="edit-code-{{ $course->id }}">Course Code</label>
                                            <input class="form-control text-uppercase" id="edit-code-{{ $course->id }}"
                                                   name="code" value="{{ $course->code }}" required maxlength="50">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold" for="edit-name-{{ $course->id }}">Program / Course Title</label>
                                            <input class="form-control" id="edit-name-{{ $course->id }}"
                                                   name="name" value="{{ $course->name }}" required maxlength="255">
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="edit-active-{{ $course->id }}"
                                                   name="is_active" value="1" @checked($course->is_active)>
                                            <label class="form-check-label fw-semibold" for="edit-active-{{ $course->id }}">
                                                Active Program (Available in Student Registration)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-save me-1"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No programs found. Click "+ Add New Program / Course" to register one.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add New Course Modal --}}
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="addCourseLabel">Add New Program / Course</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="{{ route(auth()->user()->role.'.settings.course') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="new-course-code">Course Code <span class="text-danger">*</span></label>
                        <input class="form-control text-uppercase" id="new-course-code" name="code"
                               placeholder="e.g. BSIT, BSHM" required maxlength="50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="new-course-name">Program / Course Title <span class="text-danger">*</span></label>
                        <input class="form-control" id="new-course-name" name="name"
                               placeholder="e.g. Bachelor of Science in Information Technology" required maxlength="255">
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="new-course-active" name="is_active" value="1" checked>
                        <label class="form-check-label fw-semibold" for="new-course-active">
                            Set as Active immediately
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Add Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@elseif(request('tab') === 'staff')
{{-- ── STAFF ACCOUNTS TAB ── --}}
<div class="card page-card shadow-sm p-4">
    <h2 class="h5 section-title mb-2">Create Admin or Staff Account</h2>
    <p class="text-muted small">A secure password is generated automatically and sent through Brevo after you re-verify and approve the account.</p>
    <form id="create-account-form" method="post" action="{{ route(auth()->user()->role.'.settings.user') }}" class="row g-2 align-items-end mb-4">
        @csrf
        <div class="col-lg-3">
            <label class="form-label form-label-sm fw-semibold" for="new-account-name">Name</label>
            <input id="new-account-name" name="name" class="form-control form-control-sm" value="{{ old('name') }}" required maxlength="255">
        </div>
        <div class="col-lg-3">
            <label class="form-label form-label-sm fw-semibold" for="new-account-email">Email</label>
            <input id="new-account-email" type="email" name="email" class="form-control form-control-sm" value="{{ old('email') }}" required>
        </div>
        <div class="col-lg-2">
            <label class="form-label form-label-sm fw-semibold" for="new-account-role">Account type</label>
            <select id="new-account-role" name="role" class="form-select form-select-sm" required>
                <option value="staff" @selected(old('role', 'staff') === 'staff')>Staff</option>
                <option value="admin" @selected(old('role') === 'admin')>Admin</option>
            </select>
        </div>
        <div class="col-lg-4">
            <button type="button" class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#approveAccountModal">
                Create Account
            </button>
        </div>

        <div class="modal fade" id="approveAccountModal" tabindex="-1" aria-labelledby="approveAccountLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title h5" id="approveAccountLabel">Re-verify and approve</h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            Approving <strong id="approval-role">Staff</strong>: <strong id="approval-name">—</strong>
                            (<span id="approval-email">—</span>). Brevo will email the generated password immediately.
                        </div>
                        <label class="form-label fw-semibold" for="admin-password">Your admin password</label>
                        <input id="admin-password" form="create-account-form" type="password" name="admin_password" class="form-control" required autocomplete="current-password">
                        <div class="form-text">Required to confirm that you authorize this account.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="create-account-form" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i> Approve &amp; Send Email
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <h2 class="h5 section-title mb-2">Registered Admin and Staff Accounts</h2>
    <p class="text-muted small mb-3">Leave password blank to retain existing credentials. Disabled accounts remain preserved in historical audit logs.</p>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($users as $staff)
                <tr>
                    <td class="fw-semibold">{{ $staff->name }}</td>
                    <td>{{ $staff->email }}</td>
                    <td><span class="badge bg-light text-dark border">{{ ucfirst($staff->role) }}</span></td>
                    <td>
                        <span class="badge bg-{{ $staff->is_active ? 'success' : 'secondary' }}">
                            {{ $staff->is_active ? 'Active' : 'Disabled' }}
                        </span>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-staff-{{ $staff->id }}">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </button>
                    </td>
                </tr>
                <tr class="collapse bg-light" id="edit-staff-{{ $staff->id }}">
                    <td colspan="5" class="p-3">
                        <form method="post" action="{{ route(auth()->user()->role.'.settings.user', $staff) }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="is_active" value="0">
                            <div class="col-md-3">
                                <label class="form-label form-label-sm fw-semibold">Name</label>
                                <input name="name" value="{{ $staff->name }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm fw-semibold">Email</label>
                                <input type="email" name="email" value="{{ $staff->email }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm fw-semibold">Reset Password</label>
                                <input type="password" name="password" minlength="8" class="form-control form-control-sm" autocomplete="new-password" placeholder="Leave blank to keep (min. 8 chars)">
                            </div>
                            <div class="col-md-2">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="staff-active-{{ $staff->id }}" @checked($staff->is_active)>
                                    <label class="form-check-label small fw-semibold" for="staff-active-{{ $staff->id }}">Enabled</label>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-primary btn-sm w-100">Save</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('approveAccountModal')?.addEventListener('show.bs.modal', () => {
    document.getElementById('approval-name').textContent = document.getElementById('new-account-name').value || '—';
    document.getElementById('approval-email').textContent = document.getElementById('new-account-email').value || '—';
    const role = document.getElementById('new-account-role');
    document.getElementById('approval-role').textContent = role.options[role.selectedIndex].text;
});
</script>
@endpush
@endif

@endsection
