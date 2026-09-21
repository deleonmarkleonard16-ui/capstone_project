@extends('layouts.app')
@section('content')

{{-- ══════════════════════════════════════════════════════════════
     MASTERLIST ENCODING SHEET — Screenshot 2 UI
     Excel-like editable spreadsheet for direct applicant data entry.
     Cycle selector & quick draft addition.
     ══════════════════════════════════════════════════════════════ --}}

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Applicant Masterlist</h1>
        <p class="text-muted mb-0 small">
            Current admission cycle: <strong>{{ $cycle->displayName }}</strong>
            @if ($isLocked)
                <span class="badge bg-secondary ms-2"><i class="bi bi-lock-fill me-1"></i>Archived / Read-Only</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.sessions.index') }}">
            <i class="bi bi-calendar3 me-1"></i> Open Test Sessions
        </a>
        <a class="btn btn-outline-secondary btn-sm"
           href="{{ route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary', 'format' => 'docx']) }}">
            <i class="bi bi-file-earmark-word me-1"></i> Download DOCX
        </a>
        <a class="btn btn-primary btn-sm" href="{{ route('admin.admission.masterlist', ['cycle_id' => $cycle->id]) }}">
            <i class="bi bi-table me-1"></i> Admission Masterlist
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- ── LEFT: ADMISSION CYCLE CARD (MATCHING SCREENSHOT 2) ── --}}
    <div class="col-md-5">
        <div class="card page-card shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 section-title mb-3">Admission Cycle</h2>

                {{-- Cycle Selector --}}
                <form method="get" action="{{ route('admin.admission.encoding-sheet') }}" class="d-flex gap-2 mb-3">
                    <select class="form-select" name="cycle_id" id="cycle-select">
                        @foreach($allCycles as $c)
                            <option value="{{ $c->id }}" @selected($cycle->id === $c->id)>
                                {{ $c->displayName }} {{ $c->isActive() ? '(Active)' : ($c->isCompleted() ? '(Archived)' : '(Draft)') }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-primary" type="submit">Open</button>
                </form>

                {{-- Quick Add New Cycle --}}
                <form method="post" action="{{ route('admin.admission.cycles.save') }}" class="d-flex gap-2" id="add-cycle-form">
                    @csrf
                    <input name="cycle_name" class="form-control" placeholder="Example: AY 2026-2027" required style="flex:1">
                    <input type="hidden" name="passing_stanine" value="4">
                    <input type="hidden" name="exam_weight" value="60">
                    <input type="hidden" name="gwa_weight" value="20">
                    <input type="hidden" name="interview_weight" value="20">
                    <select name="status" class="form-select" style="width:110px">
                        <option value="Draft">Draft</option>
                        <option value="Active">Active</option>
                    </select>
                    <button class="btn btn-outline-primary">Add</button>
                </form>
                <div class="mt-2">
                    <a class="text-muted small" href="{{ route('admin.admission.index') }}">
                        <i class="bi bi-gear me-1"></i> Full cycle configuration &amp; quotas →
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT: IMPORT APPLICANTS FROM EXCEL CSV (MATCHING SCREENSHOT 2) ── --}}
    <div class="col-md-7">
        <div class="card page-card shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 section-title mb-1">Import Applicants From Excel CSV</h2>
                <p class="text-muted small mb-2">
                    Upload a CSV using the same format as the Masterlist Encoding Sheet. Imported applicants will be added to the selected batch group and will appear in the encoding sheet for that batch right away.
                </p>
                @if($isLocked)
                    <div class="alert alert-secondary py-2 small mb-0">
                        <i class="bi bi-lock-fill me-1"></i> CSV import is disabled because <strong>{{ $cycle->displayName }}</strong> is archived.
                    </div>
                @else
                    <form method="post"
                          enctype="multipart/form-data"
                          action="{{ route('admin.admission.applicants.import') }}"
                          class="d-flex flex-column gap-2">
                        @csrf
                        <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
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
                            <input type="file" name="file" accept=".csv,.xlsx" class="form-control" style="flex:1" required>
                        </div>
                        <button class="btn btn-primary btn-sm">
                            <i class="bi bi-upload me-1"></i> Import Applicants
                        </button>
                    </form>
                @endif
                <p class="text-muted small mt-2 mb-0">
                    Batch group names are managed in Settings so the same options stay available across the current admission cycle.
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ── ENCODING SHEET GRID ── --}}
<div class="card page-card shadow-sm">
    <div class="card-body p-0">
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom flex-wrap gap-2">
            <div>
                <h2 class="h5 section-title mb-0">Masterlist Encoding Sheet</h2>
                <p class="text-muted small mb-0">
                    Choose a batch group first so the sheet can load the applicants already encoded for that batch in {{ $cycle->displayName }}.
                </p>
            </div>
            <div class="d-flex gap-2">
                <form method="get" action="{{ route('admin.admission.encoding-sheet') }}" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
                    <input type="hidden" name="batch_group" value="{{ request('batch_group') }}">
                    <button class="btn btn-outline-secondary btn-sm" name="sort" value="course_gwa">
                        <i class="bi bi-sort-down me-1"></i> Sort Course / Highest GWA
                    </button>
                </form>
                @unless($isLocked)
                    <button class="btn btn-primary btn-sm" form="encoding-form" type="submit">
                        <i class="bi bi-save me-1"></i> Save Encoded Rows
                    </button>
                @endunless
            </div>
        </div>

        {{-- Batch group selector --}}
        <div class="px-4 py-2 border-bottom d-flex gap-3 align-items-center bg-light flex-wrap">
            <label class="fw-semibold small mb-0">Batch Group</label>
            <select class="form-select form-select-sm" id="batch-group-select" style="max-width:260px"
                    onchange="loadBatchGroup(this.value)">
                <option value="">Select batch group</option>
                @foreach($batchGroups as $bg)
                    <option value="{{ $bg }}" @selected(request('batch_group') === $bg)>{{ $bg }}</option>
                @endforeach
                <option value="__all__" @selected(request('batch_group') === '__all__')>All batch groups</option>
            </select>
            <button class="btn btn-outline-primary btn-sm" onclick="loadBatchGroup(document.getElementById('batch-group-select').value)">
                Open Batch
            </button>
            <span class="text-muted small">
                Click 'Open Batch' after choosing a batch group, or choose 'All batch groups' to load the full cycle list sorted across all batches.
            </span>
        </div>

        {{-- Spreadsheet grid --}}
        <form id="encoding-form" method="post" action="{{ route('admin.admission.encoding-sheet.save') }}">
            @csrf
            <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
            <input type="hidden" name="batch_group" value="{{ request('batch_group') }}">

            <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                <table class="table table-sm table-bordered mb-0" id="encoding-grid" style="min-width: 900px;">
                    <thead class="table-dark sticky-top" style="top:0;z-index:10">
                        <tr>
                            <th style="width:42px" class="text-center">#</th>
                            <th>LAST NAME</th>
                            <th>GIVEN NAME</th>
                            <th>MIDDLE NAME</th>
                            <th>COURSE (1ST CHOICE)</th>
                            <th style="width:90px">SEX</th>
                            <th style="width:140px">4PS/OSY/IP/PWD/SP</th>
                            <th style="width:100px">CMFL</th>
                            <th style="width:90px">GWA</th>
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
                                       placeholder="Last name" autocomplete="off" @disabled($isLocked)>
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][first_name]"
                                       value="{{ $row->first_name ?? '' }}"
                                       placeholder="Given name" autocomplete="off" @disabled($isLocked)>
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][middle_name]"
                                       value="{{ $row->middle_name ?? '' }}"
                                       placeholder="Middle name" autocomplete="off" @disabled($isLocked)>
                            </td>
                            <td>
                                <select class="form-select form-select-sm border-0"
                                        name="rows[{{ $i }}][course_choice]" @disabled($isLocked)>
                                    <option value="">— Select —</option>
                                    @foreach($courses as $code => $title)
                                        <option value="{{ $code }}" @selected(($row->course_choice ?? '') === $code)>
                                            {{ $title }} ({{ $code }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm border-0"
                                        name="rows[{{ $i }}][sex]" @disabled($isLocked)>
                                    <option value="">—</option>
                                    <option value="Male" @selected(($row->sex ?? '') === 'Male')>Male</option>
                                    <option value="Female" @selected(($row->sex ?? '') === 'Female')>Female</option>
                                </select>
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][special_group]"
                                       value="{{ $row->special_group ?? '' }}"
                                       placeholder="N/A" autocomplete="off" @disabled($isLocked)>
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][cmfl]"
                                       value="{{ $row->cmfl ?? '' }}"
                                       placeholder="N/A" autocomplete="off" @disabled($isLocked)>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="75" max="100"
                                       class="form-control form-control-sm border-0 px-1"
                                       name="rows[{{ $i }}][gwa]"
                                       value="{{ $row->gwa ?? '' }}"
                                       placeholder="e.g. 92" autocomplete="off" @disabled($isLocked)>
                            </td>
                        </tr>
                        @endforeach
                    @endif

                    {{-- 20 direct-entry rows appended if not locked --}}
                    @unless($isLocked)
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
                                        <option value="{{ $code }}">{{ $title }} ({{ $code }})</option>
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
                    @endunless
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    @if($isLocked)
                        <i class="bi bi-lock-fill me-1"></i> Cycle is completed / archived. Encoding new rows is disabled.
                    @else
                        <i class="bi bi-info-circle me-1"></i> Empty rows are ignored. Tab through cells to navigate.
                    @endif
                </span>
                @unless($isLocked)
                    <button class="btn btn-primary btn-sm">
                        <i class="bi bi-save me-1"></i> Save Encoded Rows
                    </button>
                @endunless
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

document.addEventListener('DOMContentLoaded', function () {
    const grid = document.getElementById('encoding-grid');
    if (!grid) return;

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
