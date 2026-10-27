@extends('layouts.app')
@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Audit Logs</h1>
    <p class="text-muted mb-0 small">Successful changes made by administrators and staff.</p>
</div>

<div class="card page-card shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="GET" class="row g-2 align-items-end mb-4">
            <div class="col-md-4">
                <label for="search" class="form-label small">User or action</label>
                <input id="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Name, action, or route">
            </div>
            <div class="col-md-2">
                <label for="role" class="form-label small">Role</label>
                <select id="role" name="role" class="form-select">
                    <option value="">All roles</option>
                    <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
                    <option value="staff" @selected(($filters['role'] ?? '') === 'staff')>Staff</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="from" class="form-label small">From</label>
                <input id="from" type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="to" class="form-label small">To</label>
                <input id="to" type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary">Filter</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.audit-logs') }}">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Date and time</th><th>User</th><th>Action</th><th>Record</th><th>Request</th><th>Result</th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? $log->user_name ?? 'Deleted user' }}<div class="small text-muted text-uppercase">{{ $log->user_role }}</div></td>
                        <td>{{ $log->action }}</td>
                        <td class="small">@if($log->subject){{ collect($log->subject)->map(fn ($value, $key) => "$key: $value")->join(', ') }}@else—@endif</td>
                        <td><span class="badge text-bg-light">{{ $log->method }}</span> <span class="small">{{ $log->route ?? '—' }}</span></td>
                        <td>{{ $log->response_status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No audit entries found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
</div>
@endsection
