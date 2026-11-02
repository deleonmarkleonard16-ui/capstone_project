@extends('layouts.app')

@push('styles')
<style>
    .audit-page { color: #17243b; }
    .audit-heading-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 12px; background: #edf3ff; color: var(--psu-blue); font-size: 1.3rem; }
    .audit-card { border: 1px solid #dfe6f1; border-radius: 14px; background: rgba(255,255,255,.98); box-shadow: 0 8px 24px rgba(15,63,151,.06); overflow: hidden; }
    .audit-toolbar { padding: 14px; border-bottom: 1px solid #dfe6f1; background: #fbfcff; }
    .audit-toolbar .form-control, .audit-toolbar .form-select, .audit-toolbar .btn { min-height: 38px; font-size: .78rem; }
    .audit-search { min-width: 240px; }
    .audit-table { margin-bottom: 0; font-size: .75rem; color: #17243b; }
    .audit-table thead th { padding: 11px 10px; border-bottom: 1px solid #d7deea; background: #f0f3f9; color: #33415c; font-size: .68rem; letter-spacing: 0; text-transform: none; white-space: nowrap; }
    .audit-table tbody td { padding: 10px; border-color: #e4e9f1; vertical-align: middle; }
    .audit-table tbody tr:hover { background: #f8faff; }
    .audit-id { color: #526078; font-weight: 700; white-space: nowrap; }
    .audit-role, .audit-status { display: inline-flex; align-items: center; gap: 4px; padding: 3px 7px; border-radius: 999px; font-size: .64rem; font-weight: 700; white-space: nowrap; }
    .audit-role-admin { color: #174b91; background: #e8f1ff; border: 1px solid #bfd5f8; }
    .audit-role-staff { color: #286538; background: #e9f7ec; border: 1px solid #bde1c5; }
    .audit-status-success { color: #087443; background: #e6f7ee; }
    .audit-status-denied { color: #9a5700; background: #fff1d6; }
    .audit-status-failed { color: #b42318; background: #feeceb; }
    .audit-detail { max-width: 190px; white-space: normal; color: #536078; }
    .audit-footer { padding: 10px 14px; border-top: 1px solid #dfe6f1; background: #fbfcff; font-size: .72rem; }
    .audit-footer nav { margin-left: auto; }
    .audit-footer .pagination { margin-bottom: 0; }
    .audit-footer .page-link { font-size: .7rem; padding: .3rem .55rem; }
    @media (max-width: 767.98px) { .audit-search { min-width: 100%; } .audit-toolbar .btn { flex: 1; } }
</style>
@endpush

@section('content')
<div class="audit-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="audit-heading-icon"><i class="bi bi-clock-history"></i></div>
            <div>
                <h1 class="h3 fw-bold mb-0">Audit Logs</h1>
                <p class="text-muted mb-0 small">Track and monitor system activities for security and accountability.</p>
            </div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.audit-logs', array_merge(request()->except('page', 'export'), ['export' => 'csv'])) }}">
            <i class="bi bi-download me-1"></i> Export Logs
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
    @endif

    <div class="audit-card mb-4">
        <form method="GET" class="audit-toolbar d-flex flex-wrap gap-2 align-items-center">
            <div class="input-group audit-search flex-grow-1">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input id="search" name="search" class="form-control border-start-0 ps-0" value="{{ $filters['search'] ?? '' }}" placeholder="Search by user, activity, or route">
            </div>
            <select id="role" name="role" class="form-select" style="width:auto" aria-label="Filter by role">
                <option value="">All roles</option>
                <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Administrator</option>
                <option value="staff" @selected(($filters['role'] ?? '') === 'staff')>Staff</option>
                <option value="guest" @selected(($filters['role'] ?? '') === 'guest')>Guest (unauthenticated)</option>
            </select>
            <select id="status" name="status" class="form-select" style="width:auto" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option value="success" @selected(($filters['status'] ?? '') === 'success')>Success</option>
                <option value="denied" @selected(($filters['status'] ?? '') === 'denied')>Denied</option>
                <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
            </select>
            <div class="input-group" style="width:auto">
                <span class="input-group-text bg-white text-muted"><i class="bi bi-calendar3"></i></span>
                <input id="from" type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}" aria-label="From date">
            </div>
            <div class="input-group" style="width:auto">
                <span class="input-group-text bg-white text-muted"><i class="bi bi-calendar3"></i></span>
                <input id="to" type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}" aria-label="To date">
            </div>
            <button class="btn btn-primary px-3"><i class="bi bi-search me-1"></i> Search</button>
            @if(collect($filters)->except('export')->filter()->isNotEmpty())
                <a class="btn btn-outline-secondary" href="{{ route('admin.audit-logs') }}" aria-label="Clear filters"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table audit-table align-middle">
                <thead><tr><th>ID</th><th>User</th><th>Role</th><th>Activity</th><th>Module</th><th>Date &amp; Time</th><th>IP Address</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    @php
                        $status = $log->response_status < 400 ? 'success' : (in_array($log->response_status, [401, 403], true) ? 'denied' : 'failed');
                        $module = str($log->route ?: 'System')->before('.')->replace('-', ' ')->headline();
                    @endphp
                    <tr>
                        <td class="audit-id">LOG-{{ str_pad($log->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td class="fw-semibold text-nowrap">{{ $log->user?->name ?? $log->user_name ?? 'Deleted user' }}</td>
                        <td><span class="audit-role audit-role-{{ $log->user_role === 'admin' ? 'admin' : 'staff' }}">{{ $log->user_role === 'admin' ? 'Administrator' : ucfirst($log->user_role ?: 'Unknown') }}</span></td>
                        <td>{{ $log->activityDescription() }}</td>
                        <td class="text-nowrap">{{ $module }}</td>
                        <td class="text-nowrap">{{ $log->created_at?->format('M j, Y, h:i A') }}</td>
                        <td class="text-nowrap">{{ $log->ip_address ?: '—' }}</td>
                        <td><span class="audit-status audit-status-{{ $status }}"><i class="bi bi-{{ $status === 'success' ? 'check-circle-fill' : ($status === 'denied' ? 'exclamation-triangle-fill' : 'x-circle-fill') }}"></i>{{ ucfirst($status) }}</span></td>
                        <td class="audit-detail">@if($log->subject){{ collect($log->subject)->map(fn ($value, $key) => str($key)->replace('_', ' ')->headline().': '.$value)->join(', ') }}@else—@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-journal-x d-block fs-3 mb-2"></i>No audit entries match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="audit-footer d-flex flex-wrap align-items-center gap-3">
            {{ $logs->onEachSide(1)->links() }}
        </div>
    </div>
</div>
@endsection
