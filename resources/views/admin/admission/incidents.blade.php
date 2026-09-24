@extends('layouts.app')
@section('content')

{{-- ══════════════════════════════════════════════════════════
     PSU-CAT PROCTORING DASHBOARD
     Real-time security incident feed from guidance_test_security_logs.
     Polls every 4 seconds; dispatches Toast alerts for each new strike.
     ══════════════════════════════════════════════════════════ --}}

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-camera-video me-2"></i>PSU-CAT Proctoring Dashboard</h1>
        <p class="text-muted small mb-0">
            Real-time security incident monitoring · Feed auto-refreshes every 4 seconds
            <span class="badge ms-2" id="status-badge">Connecting…</span>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.admission.index') }}">
            <i class="bi bi-arrow-left me-1"></i> Admission Setup
        </a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.admission.masterlist') }}">
            <i class="bi bi-table me-1"></i> Masterlist
        </a>
    </div>
</div>

{{-- ── STATS ROW ── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card page-card border-danger h-100">
            <div class="card-body p-3">
                <p class="small text-muted mb-1">Total Incidents</p>
                <h2 class="h4 mb-0 text-danger" id="stat-total">0</h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card border-warning h-100">
            <div class="card-body p-3">
                <p class="small text-muted mb-1">Back Button / Tab Switch</p>
                <h2 class="h4 mb-0 text-warning" id="stat-back">0</h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card border-info h-100">
            <div class="card-body p-3">
                <p class="small text-muted mb-1">Screenshot / Print Attempts</p>
                <h2 class="h4 mb-0 text-info" id="stat-screenshot">0</h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card border-danger h-100">
            <div class="card-body p-3">
                <p class="small text-muted mb-1">Terminated (3 Strikes)</p>
                <h2 class="h4 mb-0 text-danger" id="stat-terminated">0</h2>
            </div>
        </div>
    </div>
</div>

{{-- ── INCIDENT FEED TABLE ── --}}
<div class="card page-card shadow-sm">
    <div class="card-header bg-white py-3">
        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-shield-exclamation me-1 text-danger"></i> Live Security Incidents</h2>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="incidents-table">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3" style="width:90px;">Time</th>
                        <th>Applicant</th>
                        <th style="width:120px;">App No.</th>
                        <th>Course</th>
                        <th>Incident Type</th>
                        <th class="text-center" style="width:90px;">Strike</th>
                    </tr>
                </thead>
                <tbody id="incidents-body">
                    <tr id="empty-row">
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-shield-check fs-2 d-block mb-2 opacity-25"></i>
                            Feed is live — no security incidents recorded yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── TOAST CONTAINER ── --}}
<div class="toast-container position-fixed top-0 end-0 p-3" id="toast-container" style="z-index:9999;"></div>

<script>
const INCIDENTS_URL = @json(route('admin.admission.incidents'));

const LABELS = {
    back_button:      'Back Button Pressed',
    print_screen:     'Print Screen Key',
    print:            'Ctrl+P / Print Attempt',
    focus_loss:       'Window Focus Lost',
    fullscreen_exit:  'Exited Fullscreen',
    tab_switch:       'Tab / App Switch',
    screenshot:       'Screenshot Attempt',
};

let cursor    = 0;
let seenIds   = new Set();
let stats     = { total: 0, back: 0, screenshot: 0, terminated: 0 };

// ── HELPERS ────────────────────────────────────────────────
function fmt(iso) {
    return new Date(iso).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function rowClass(strike) {
    if (strike >= 3) return 'table-danger';
    if (strike === 2) return 'table-warning';
    return '';
}

function strikeBadge(strike) {
    const cls = strike >= 3 ? 'bg-danger' : strike === 2 ? 'bg-warning text-dark' : 'bg-secondary';
    return `<span class="badge ${cls}">${strike} / 3</span>`;
}

function addToast(item) {
    const isTerminated = item.strike_number >= 3;
    const toast = document.createElement('div');
    toast.className = `toast show border-0 text-bg-${isTerminated ? 'danger' : 'warning'}`;
    toast.style.minWidth = '280px';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="toast-body fw-bold d-flex justify-content-between align-items-start gap-2">
            <span>
                <i class="bi bi-shield-exclamation me-1"></i>
                Strike ${item.strike_number}/3
                ${isTerminated ? '— <strong>EXAM TERMINATED</strong>' : ''}
                <br><small class="fw-normal">${(item.last_name || '').toUpperCase()}, ${item.first_name || ''} — ${LABELS[item.incident_type] || item.incident_type}</small>
            </span>
            <button type="button" class="btn-close ${isTerminated ? 'btn-close-white' : ''} ms-2 flex-shrink-0"
                    onclick="this.closest('.toast').remove()"></button>
        </div>`;
    document.getElementById('toast-container').prepend(toast);
    setTimeout(() => { try { toast.remove(); } catch (e) {} }, 8000);
}

// ── POLLING ────────────────────────────────────────────────
async function refresh() {
    try {
        const res = await fetch(INCIDENTS_URL + '?after=' + cursor, {
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) throw new Error('feed-error');

        const items = await res.json();
        const tbody = document.getElementById('incidents-body');
        const emptyRow = document.getElementById('empty-row');

        for (const item of items) {
            cursor = Math.max(cursor, item.log_id);
            if (seenIds.has(item.log_id)) continue;
            seenIds.add(item.log_id);

            // Update stats
            stats.total++;
            if (['back_button','tab_switch','fullscreen_exit','focus_loss'].includes(item.incident_type)) stats.back++;
            if (['print_screen','print','screenshot'].includes(item.incident_type)) stats.screenshot++;
            if (item.strike_number >= 3) stats.terminated++;

            document.getElementById('stat-total').textContent      = stats.total;
            document.getElementById('stat-back').textContent       = stats.back;
            document.getElementById('stat-screenshot').textContent = stats.screenshot;
            document.getElementById('stat-terminated').textContent = stats.terminated;

            // Remove empty-state placeholder
            if (emptyRow) emptyRow.remove();

            // Insert new row at top
            const tr = document.createElement('tr');
            tr.className = rowClass(item.strike_number);
            tr.innerHTML = `
                <td class="ps-3 text-nowrap small">${fmt(item.created_at)}</td>
                <td class="fw-semibold">${(item.last_name || '').toUpperCase()}, ${item.first_name || ''}</td>
                <td class="font-monospace small">${item.application_number || '—'}</td>
                <td><span class="badge bg-light text-dark border small">${item.course_choice || '—'}</span></td>
                <td>${LABELS[item.incident_type] || item.incident_type}</td>
                <td class="text-center">${strikeBadge(item.strike_number)}</td>
            `;
            tbody.prepend(tr);
            addToast(item);
        }

        document.getElementById('status-badge').textContent  = 'Live · ' + new Date().toLocaleTimeString();
        document.getElementById('status-badge').className    = 'badge bg-success ms-2';
    } catch {
        document.getElementById('status-badge').textContent  = 'Feed error – retrying';
        document.getElementById('status-badge').className    = 'badge bg-danger ms-2';
    }
}

// Initial load + 4-second interval
refresh();
setInterval(refresh, 4000);
// Re-poll immediately when user returns to tab
document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
</script>
@endsection
