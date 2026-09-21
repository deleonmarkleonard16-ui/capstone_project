@extends('layouts.app')
@section('content')

{{-- ══════════════════════════════════════════════════════════════
     MASTERLIST ENCODING SHEET — Screenshot 1 UI
     Excel-like editable spreadsheet for direct applicant data entry.
     Batch group selection gates which applicants are shown.
     ══════════════════════════════════════════════════════════════ --}}

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Applicant Masterlist</h1>
        <p class="text-muted mb-0 small">
            Current admission cycle: <strong>{{ $cycle->name }}</strong>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.sessions.index') }}">
            <i class="bi bi-calendar3 me-1"></i> Open Test Sessions
        </a>
        <a class="btn btn-outline-secondary btn-sm"
           href="{{ route('admin.admission.report', ['type'=>'summary','format'=>'docx']) }}">
            <i class="bi bi-file-earmark-word me-1"></i> Download DOCX
        </a>
        <a class="btn btn-primary btn-sm" href="{{ route('admin.admission.masterlist') }}">
            <i class="bi bi-table me-1"></i> Admission Masterlist
        </a>
    </div>
</div>

<div class="row g-3 mb-4">

    {{-- ── LEFT: ADMISSION CYCLE PANEL ── --}}
    <div class="col-md-5">
        <div class="card page-card h-100">
            <div class="card-body p-4">
                <h2 class="h5 section-title mb-3">Admission Cycle</h2>

                {{-- Active cycle selector --}}
                <div class="d-flex gap-2 mb-3">
                    <select class="form-select" id="cycle-select" name="cycle_id">
                        <option value="{{ $cycle->id }}" selected>{{ $cycle->name }} (Active)</option>
                    </select>
                    <a class="btn btn-outline-primary"
                       href="{{ route('admin.admission.index') }}">Open</a>
                </div>

                {{-- Add new cycle (draft) --}}
                <form method="post" action="{{ route('admin.admission.cycles.save') }}"
                      class="d-flex gap-2" id="add-cycle-form">
                    @csrf
                    <input name="name" class="form-control" placeholder="Example: AY 2026-2027"
                           required style="flex:1">
                    {{-- Hidden sensible defaults so the form can submit --}}
                    <input type="hidden" name="academic_year" value="{{ now()->year.'-'.(now()->year+1) }}">
                    <input type="hidden" name="passing_stanine" value="4">
                    <input type="hidden" name="exam_weight" value="60">
                    <input type="hidden" name="gwa_weight" value="20">
                    <input type="hidden" name="interview_weight" value="20">
                    <select name="_status" class="form-select" style="width:110px">
                        <option value="draft">Draft</option>
                    </select>
                    <button class="btn btn-outline-primary">Add</button>
                </form>
                <div class="mt-2">
                    <a class="text-muted small" href="{{ route('admin.admission.index') }}">
                        <i class="bi bi-gear me-1"></i> Full cycle configuration →
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT: CSV IMPORT PANEL ── --}}
    <div class="col-md-7">
        <div class="card page-card h-100">
            <div class="card-body p-4">
                <h2 class="h5 section-title mb-1">Import Applicants From Excel CSV</h2>
                <p class="text-muted small mb-3">
                    Upload a CSV using the same format as the Masterlist Encoding Sheet.
                    Imported applicants will be added to the selected batch group and will appear
                    in the encoding sheet for that batch right away.
                </p>
                <form method="post"
                      enctype="multipart/form-data"
                      action="{{ route('admin.admission.applicants.import') }}"
                      class="d-flex flex-column gap-2">
                    @csrf
                    <code class="d-block p-2 bg-light rounded border small">
                        Last Name, Given Name, Middle Name, Course (1st choice), Sex, 4PS/OSY/IP/PWD/SP, CMFL, GWA
                    </code>
                    <div class="d-flex gap-2">
                        <select class="form-select" name="batch_group" style="flex:1">
                            <option value="">Select batch group</option>
                            @foreach($batchGroups as $bg)
                                <option value="{{ $bg }}">{{ $bg }}</option>
                            @endforeach
                        </select>
                        <input type="file" name="file" accept=".csv,.xlsx"
                               class="form-control" style="flex:1">
                    </div>
                    <button class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i> Import Applicants
                    </button>
                </form>
                <p class="text-muted small mt-2 mb-0">
                    Batch group names are managed in Settings so the same options stay available
                    across the current admission cycle.
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ── ENCODING SHEET GRID ── --}}
<div class="card page-card">
    <div class="card-body p-0">
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
            <div>
                <h2 class="h5 section-title mb-0">Masterlist Encoding Sheet</h2>
                <p class="text-muted small mb-0">
                    Choose a batch group first so the sheet can load the applicants already encoded
                    for that batch in {{ $cycle->name }}.
                </p>
            </div>
            <div class="d-flex gap-2">
                <form method="get" action="{{ route('admin.admission.encoding-sheet') }}"
                      class="d-flex gap-2 align-items-center" id="sort-form">
                    <input type="hidden" name="batch_group" id="sort-batch-hidden"
                           value="{{ request('batch_group') }}">
                    <button class="btn btn-outline-secondary btn-sm" name="sort" value="course_gwa">
                        <i class="bi bi-sort-down me-1"></i> Sort Course / Highest GWA
                    </button>
                </form>
                <button class="btn btn-success btn-sm" form="encoding-form" type="submit">
                    <i class="bi bi-save me-1"></i> Save Encoded Rows
                </button>
            </div>
        </div>

        {{-- Batch group selector --}}
        <div class="px-4 py-2 border-bottom d-flex gap-3 align-items-center bg-light">
            <label class="fw-semibold small mb-0">Batch Group</label>
            <select class="form-select form-select-sm" id="batch-group-select" style="max-width:280px"
                    onchange="loadBatchGroup(this.value)">
                <option value="">Select batch group</option>
                @foreach($batchGroups as $bg)
                    <option value="{{ $bg }}"
                        @selected(request('batch_group') === $bg)>
                        {{ $bg }}
                    </option>
                @endforeach
                <option value="__all__" @selected(request('batch_group') === '__all__')>
                    All batch groups
                </option>
            </select>
            <button class="btn btn-outline-primary btn-sm" onclick="loadBatchGroup(document.getElementById('batch-group-select').value)">
                <i class="bi bi-folder2-open me-1"></i> Open Batch
            </button>
            <span class="text-muted small">
                Click 'Open Batch' after choosing a batch group, or choose 'All batch groups'
                to load the full cycle list sorted across all batches.
            </span>
        </div>

        {{-- Spreadsheet grid --}}
        <form id="encoding-form" method="post" action="{{ route('admin.admission.encoding-sheet.save') }}">
            @csrf
            <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                <table class="table table-sm table-bordered mb-0" id="encoding-grid" style="min-width: 900px;">
                    <thead class="table-dark sticky-top" style="top:0;z-index:10">
                        <tr>
                            <th style="width:42px" class="text-center">#</th>
                            <th>Last Name</th>
                            <th>Given Name</th>
                            <th>Middle Name</th>
                            <th>Course (1st Choice)</th>
                            <th style="width:80px">Sex</th>
                            <th style="width:130px">4PS/OSY/IP/PWD/SP</th>
                            <th style="width:90px">CMFL</th>
                            <th style="width:80px">GWA</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                    @if(count($rows) > 0)
                        @foreach($rows as $i => $row)
                        <tr data-row="{{ $i + 1 }}">
                            <td class="text-center text-muted small align-middle">{{ $i + 1 }}</td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][id]" value="{{ $row->id ?? '' }}">
                                <input class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][last_name]"
                                       value="{{ $row->last_name ?? '' }}"
                                       placeholder="Last name" autocomplete="off">
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][first_name]"
                                       value="{{ $row->first_name ?? '' }}"
                                       placeholder="Given name" autocomplete="off">
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][middle_name]"
                                       value="{{ $row->middle_name ?? '' }}"
                                       placeholder="Middle name" autocomplete="off">
                            </td>
                            <td>
                                <select class="form-select form-select-sm border-0"
                                        name="rows[{{ $i }}][course_choice]">
                                    <option value="">— Select —</option>
                                    @foreach($courses as $code => $title)
                                        <option value="{{ $code }}"
                                            @selected(($row->course_choice ?? '') === $code)>
                                            {{ $title }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm border-0"
                                        name="rows[{{ $i }}][sex]">
                                    <option value="">—</option>
                                    <option value="Male" @selected(($row->sex ?? '') === 'Male')>Male</option>
                                    <option value="Female" @selected(($row->sex ?? '') === 'Female')>Female</option>
                                </select>
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][special_group]"
                                       value="{{ $row->special_group ?? '' }}"
                                       placeholder="N/A" autocomplete="off">
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][cmfl]"
                                       value="{{ $row->cmfl ?? '' }}"
                                       placeholder="N/A" autocomplete="off">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="75" max="100"
                                       class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][gwa]"
                                       value="{{ $row->gwa ?? '' }}"
                                       placeholder="e.g. 92" autocomplete="off">
                            </td>
                        </tr>
                        @endforeach
                    @endif

                    {{-- Always include 20 blank rows for direct entry --}}
                    @php $offset = count($rows); @endphp
                    @for ($j = 0; $j < 20; $j++)
                    @php $idx = $offset + $j; @endphp
                    <tr data-row="{{ $idx + 1 }}" class="empty-row">
                        <td class="text-center text-muted small align-middle">{{ $idx + 1 }}</td>
                        <td>
                            <input class="form-control form-control-sm border-0 px-1"
                                   name="rows[{{ $idx }}][last_name]"
                                   placeholder="Last name" autocomplete="off">
                        </td>
                        <td>
                            <input class="form-control form-control-sm border-0 px-1"
                                   name="rows[{{ $idx }}][first_name]"
                                   placeholder="Given name" autocomplete="off">
                        </td>
                        <td>
                            <input class="form-control form-control-sm border-0 px-1"
                                   name="rows[{{ $idx }}][middle_name]"
                                   placeholder="Middle name" autocomplete="off">
                        </td>
                        <td>
                            <select class="form-select form-select-sm border-0"
                                    name="rows[{{ $idx }}][course_choice]">
                                <option value="">— Select —</option>
                                @foreach($courses as $code => $title)
                                    <option value="{{ $code }}">{{ $title }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm border-0"
                                    name="rows[{{ $idx }}][sex]">
                                <option value="">—</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </td>
                        <td>
                            <input class="form-control form-control-sm border-0 px-1"
                                   name="rows[{{ $idx }}][special_group]"
                                   placeholder="N/A" autocomplete="off">
                        </td>
                        <td>
                            <input class="form-control form-control-sm border-0 px-1"
                                   name="rows[{{ $idx }}][cmfl]"
                                   placeholder="N/A" autocomplete="off">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="75" max="100"
                                   class="form-control form-control-sm border-0 px-1"
                                   name="rows[{{ $idx }}][gwa]"
                                   placeholder="" autocomplete="off">
                        </td>
                    </tr>
                    @endfor
                    </tbody>
                </table>
            </div>

            {{-- Batch group hidden field --}}
            <input type="hidden" name="batch_group" value="{{ request('batch_group') }}">

            <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Empty rows are ignored. Tab through cells to navigate.
                </span>
                <button class="btn btn-success">
                    <i class="bi bi-save me-1"></i> Save Encoded Rows
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function loadBatchGroup(value) {
    if (!value) return;
    const url = new URL(window.location.href);
    url.searchParams.set('batch_group', value);
    window.location.href = url.toString();
}

// Tab-navigation through the spreadsheet cells
document.addEventListener('DOMContentLoaded', function () {
    const grid = document.getElementById('encoding-grid');
    if (!grid) return;

    grid.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;
        // Default tab behaviour is sufficient — just prevent header focus loop
    });

    // Auto-highlight cell on focus
    grid.querySelectorAll('input, select').forEach(function (el) {
        el.addEventListener('focus', function () {
            this.closest('tr')?.classList.add('table-primary');
        });
        el.addEventListener('blur', function () {
            this.closest('tr')?.classList.remove('table-primary');
        });
    });
});
</script>
@endpush

@endsection
