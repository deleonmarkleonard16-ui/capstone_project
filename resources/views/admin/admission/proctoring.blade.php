@extends('layouts.app')
@section('content')

{{-- PSU-CAT PROCTORING DASHBOARD --}}
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-camera-video me-2"></i>PSU-CAT Proctoring Dashboard</h1>
        <p class="text-muted small mb-0">Real-time security incident monitoring. Incidents auto-refresh every 4 seconds.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.admission.index') }}">
            <i class="bi bi-arrow-left me-1"></i> Admission Setup
        </a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.admission.masterlist') }}">
            <i class="bi bi-table me-1"></i> Masterlist
        </a>
    </div>
</div>

{{-- STATS ROW --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card page-card border-danger h-100">
            <div class="card-body p-3">
                <p class="small text-muted mb-1">Total Incidents</p>
                <h2 class="h4 mb-0 text-danger" id="stat-total">0</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card page-card border-warning h-100">
            <div class="card-body p-3">
                <p class="small text-muted mb-1">Back Button Presses</p>
                <h2 class="h4 mb-0" id="stat-back">0</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card page-card border-info h-100">
            <div class="card-body p-3">
                <p class="small text-muted mb-1">Screenshot Attempts</p>
                <h2 class="h4 mb-0" id="stat-screenshot">0</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card page-card border-secondary h-100">
            <div class="card-body p-3">
                <p class="small text-muted mb-1">Focus / Tab Switch</p>
                <h2 class="h4 mb-0" id="stat-focus">0</h2>
            </div>
        </div>
    </div>
</div>

{{-- INCIDENTS TABLE --}}
<div class="card page-card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-shield-exclamation me-1"></i> Security Incidents Feed</h2>
        <span class="badge bg-secondary" id="status-badge">Connecting...</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="incidents-table">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">Time</th>
                        <th>Applicant</th>
                        <th>App No.</th>
                        <th>Course</th>
                        <th>Incident Type</th>
                        <th class="text-center">Strike</th>
                    </tr>
                </thead>
                <tbody id="incidents-body">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-shield-check fs-2 d-block mb-2 opacity-25"></i>
                            No security incidents yet. Feed is live.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- TOAST CONTAINER --}}
<div class="toast-container position-fixed top-0 end-0 p-3" id="toast-container" style="z-index:9999;"></div>

<script>
const INCIDENTS_URL = @json(route('admin.admission.incidents'));
const INCIDENT_LABELS = {
    back_button: 'Back Button',
    print_screen: 'Print Screen',
    print: 'Ctrl+P Print',
    focus_loss: 'Focus Loss',
    fullscreen_exit: 'Fullscreen Exit',
    tab_switch: 'Tab Switch',
    screenshot: 'Screenshot',
};

let cursor = 0;
let seenIds = new Set();
let stats = { total: 0, back: 0, screenshot: 0, focus: 0 };

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function incidentColor(strike) {
    if (strike >= 3) return 'table-danger';
    if (strike === 2) return 'table-warning';
    return '';
}

function strikeBadge(strike) {
    const cls = strike >= 3 ? 'bg-danger' : strike === 2 ? 'bg-warning text-dark' : 'bg-secondary';
    return `<span class="badge ${cls}">${strike} / 3</span>`;
}

function addToast(item) {
    const toast = document.createElement('div');
    toast.className = 'toast show border-0 text-bg-' + (item.strike_number >= 3 ? 'danger' : 'warning');
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `<div class="toast-body fw-bold">
        <i class="bi bi-shield-exclamation me-1"></i>
        Strike ${item.strike_number}/3 &mdash; ${item.last_name}, ${item.first_name} &mdash;
        ${INCIDENT_LABELS[item.incident_type] || item.incident_type}
        <button type="button" class="btn-close btn-close-white float-end" onclick="this.closest('.toast').remove()"></button>
    </div>`;
    document.getElementById('toast-container').prepend(toast);
    setTimeout(() => toast.remove(), 8000);
}

async function refresh() {
    try {
        const res = await fetch(INCIDENTS_URL + '?after=' + cursor, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('Feed unavailable');
        const items = await res.json();

        const tbody = document.getElementById('incidents-body');
        let first = true;

        for (const item of items) {
            cursor = Math.max(cursor, item.log_id);
            if (seenIds.has(item.log_id)) continue;
            seenIds.add(item.log_id);

            // Update stats
            stats.total++;
            if (item.incident_type === 'back_button') stats.back++;
            if (['print_screen','print','screenshot'].includes(item.incident_type)) stats.screenshot++;
            if (['focus_loss','tab_switch','fullscreen_exit'].includes(item.incident_type)) stats.focus++;
            document.getElementById('stat-total').textContent = stats.total;
            document.getElementById('stat-back').textContent = stats.back;
            document.getElementById('stat-screenshot').textContent = stats.screenshot;
            document.getElementById('stat-focus').textContent = stats.focus;

            // Remove empty placeholder row
            if (first) { const empty = tbody.querySelector('[colspan]'); if (empty) empty.closest('tr').remove(); first = false; }

            const row = document.createElement('tr');
            row.className = incidentColor(item.strike_number);
            row.innerHTML = `
                <td class="ps-3 text-nowrap">${formatTime(item.created_at)}</td>
                <td class="fw-bold">${item.last_name?.toUpperCase()}, ${item.first_name}</td>
                <td class="font-monospace">${item.application_number ?? '—'}</td>
                <td><span class="badge bg-light text-dark border">${item.course_choice ?? '—'}</span></td>
                <td>${INCIDENT_LABELS[item.incident_type] || item.incident_type}</td>
                <td class="text-center">${strikeBadge(item.strike_number)}</td>
            `;
            tbody.prepend(row);

            addToast(item);
        }

        document.getElementById('status-badge').textContent = 'Live · Updated ' + new Date().toLocaleTimeString();
        document.getElementById('status-badge').className = 'badge bg-success';
    } catch (err) {
        document.getElementById('status-badge').textContent = 'Feed error – retrying';
        document.getElementById('status-badge').className = 'badge bg-danger';
    }
}

refresh();
setInterval(refresh, 4000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
</script>
@endsection
