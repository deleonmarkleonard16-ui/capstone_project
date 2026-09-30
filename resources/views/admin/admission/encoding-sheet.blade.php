@extends('layouts.app')
@section('content')

{{-- ══════════════════════════════════════════════════════════════
     MASTERLIST ENCODING SHEET
     Excel-like editable spreadsheet for direct applicant data entry.
     Cycle selector, CSV Importer, and 2-Choice Academic Program Routing.
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
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.admission.sessions.index') }}">
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
    {{-- ── LEFT: ADMISSION CYCLE CARD ── --}}
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

    {{-- ── RIGHT: IMPORT APPLICANTS FROM EXCEL CSV ── --}}
    <div class="col-md-7">
        <div class="card page-card shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 section-title mb-1">Import Applicants From Excel / CSV</h2>
                <p class="text-muted small mb-2">
                    Upload a CSV/XLSX using the 2-Choice degree program format. Imported applicants will be assigned to the selected batch group and evaluated immediately.
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
                            last_name, first_name, middle_name, course_choice_1, course_choice_2, sex, 4ps_osy_ip_pwd_sp, cmfl, gwa
                        </code>
                        <div class="d-flex gap-2">
                            <select class="form-select" name="batch_group" style="flex:1">
                                <option value="">Select batch group</option>
                                @foreach($batchGroups as $bg)
                                    <option value="{{ $bg }}">{{ $bg }}</option>
                                @endforeach
                            </select>
                            <input type="file" name="file" accept=".csv,.xlsx,.txt" class="form-control" style="flex:1" required>
                        </div>
                        <button class="btn btn-primary btn-sm">
                            <i class="bi bi-upload me-1"></i> Import Applicants
                        </button>
                    </form>
                @endif
                <p class="text-muted small mt-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i> Course choice 2 is optional. Values can be blank, <code>N/A</code>, or <code>None</code>.
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
                    Dual-choice applicant encoding grid for <strong>{{ $cycle->displayName }}</strong>.
                </p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <form method="get" action="{{ route('admin.admission.encoding-sheet') }}" class="d-flex gap-2 align-items-center mb-0">
                    <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
                    <input type="hidden" name="batch_group" value="{{ request('batch_group') }}">
                    <button class="btn btn-outline-secondary btn-sm" name="sort" value="course_gwa" title="Sort by Course and Highest GWA">
                        <i class="bi bi-sort-down me-1"></i> Sort Course / Highest GWA
                    </button>
                </form>
                @unless($isLocked)
                    <button class="btn btn-primary btn-sm" id="btn-save-grid" form="encoding-form" type="submit">
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
                Click 'Open Batch' after choosing a batch group, or choose 'All batch groups' to load the full cycle list.
            </span>
        </div>

        {{-- Spreadsheet grid --}}
        <form id="encoding-form" method="post" action="{{ route('admin.admission.encoding-sheet.save') }}">
            @csrf
            <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
            <input type="hidden" name="batch_group" value="{{ request('batch_group') }}">

            <div class="table-responsive" style="max-height: 62vh; overflow-y: auto;">
                <table class="table table-sm table-bordered mb-0" id="encoding-grid" style="min-width: 1100px;">
                    <thead class="table-dark sticky-top" style="top:0;z-index:10">
                        <tr>
                            <th style="width:45px" class="text-center">#</th>
                            <th style="min-width:140px">LAST NAME</th>
                            <th style="min-width:140px">GIVEN NAME</th>
                            <th style="min-width:120px">MIDDLE NAME</th>
                            <th style="min-width:190px">COURSE (1ST CHOICE)</th>
                            <th style="min-width:190px">COURSE (2ND CHOICE)</th>
                            <th style="width:95px">SEX</th>
                            <th style="width:160px">4PS/OSY/IP/PWD/SP</th>
                            <th style="width:165px">CMFL</th>
                            <th style="width:95px">GWA</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                    @php
                        $specialGroupOptions = ['N/A', '4Ps', 'OSY', 'IP', 'PWD', 'SP'];
                        $cmflOptions = ['N/A', '10,000 below', '10,001 to 20,000', '20,001 to 30,000', '30,001 to 50,000', '50,001 and above'];
                    @endphp
                    @if(count($rows) > 0)
                        @foreach($rows as $i => $row)
                        @php
                            $c1Val = $row->course_choice_1 ?? $row->course_choice ?? '';
                            $c2Val = $row->course_choice_2 ?? $row->second_course_choice ?? '';
                        @endphp
                        <tr data-row="{{ $i + 1 }}">
                            <td class="text-center text-muted small align-middle row-number">{{ $i + 1 }}</td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][id]" value="{{ $row->id ?? '' }}" class="cell-id">
                                <input class="form-control form-control-sm border-0 px-1 cell-input"
                                       name="rows[{{ $i }}][last_name]"
                                       value="{{ $row->last_name ?? '' }}"
                                       placeholder="Last name" autocomplete="off" @disabled($isLocked)>
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1 cell-input"
                                       name="rows[{{ $i }}][first_name]"
                                       value="{{ $row->first_name ?? '' }}"
                                       placeholder="Given name" autocomplete="off" @disabled($isLocked)>
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1 cell-input"
                                       name="rows[{{ $i }}][middle_name]"
                                       value="{{ $row->middle_name ?? '' }}"
                                       placeholder="Middle name" autocomplete="off" @disabled($isLocked)>
                            </td>

                            {{-- COURSE 1ST CHOICE DROPDOWN --}}
                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $i }}][course_choice_1]" @disabled($isLocked)>
                                    <option value="">— Select —</option>
                                    @foreach($courses as $code => $title)
                                        <option value="{{ $code }}" @selected($c1Val === $code)>
                                            {{ $title }} ({{ $code }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            {{-- COURSE 2ND CHOICE DROPDOWN (Optional / N/A / None) --}}
                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $i }}][course_choice_2]" @disabled($isLocked)>
                                    <option value="">— N/A (None) —</option>
                                    @foreach($courses as $code => $title)
                                        <option value="{{ $code }}" @selected($c2Val === $code)>
                                            {{ $title }} ({{ $code }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $i }}][sex]" @disabled($isLocked)>
                                    <option value="">—</option>
                                    <option value="Male" @selected(($row->sex ?? '') === 'Male')>Male</option>
                                    <option value="Female" @selected(($row->sex ?? '') === 'Female')>Female</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $i }}][special_group]" @disabled($isLocked)>
                                    @foreach($specialGroupOptions as $opt)
                                        <option value="{{ $opt }}" @selected(($row->special_group ?? 'N/A') === $opt || (!($row->special_group ?? '') && $opt === 'N/A'))>
                                            {{ $opt }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $i }}][cmfl]" @disabled($isLocked)>
                                    @foreach($cmflOptions as $opt)
                                        <option value="{{ $opt }}" @selected(($row->cmfl ?? 'N/A') === $opt || (!($row->cmfl ?? '') && $opt === 'N/A'))>
                                            {{ $opt }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="75" max="100"
                                       class="form-control form-control-sm border-0 px-1 cell-input"
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
                            <td class="text-center text-muted small align-middle row-number">{{ $idx + 1 }}</td>
                            <td>
                                <input type="hidden" name="rows[{{ $idx }}][id]" value="" class="cell-id">
                                <input class="form-control form-control-sm border-0 px-1 cell-input"
                                       name="rows[{{ $idx }}][last_name]"
                                       placeholder="Last name" autocomplete="off">
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1 cell-input"
                                       name="rows[{{ $idx }}][first_name]"
                                       placeholder="Given name" autocomplete="off">
                            </td>
                            <td>
                                <input class="form-control form-control-sm border-0 px-1 cell-input"
                                       name="rows[{{ $idx }}][middle_name]"
                                       placeholder="Middle name" autocomplete="off">
                            </td>

                            {{-- COURSE 1ST CHOICE DROPDOWN --}}
                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $idx }}][course_choice_1]">
                                    <option value="">— Select —</option>
                                    @foreach($courses as $code => $title)
                                        <option value="{{ $code }}">{{ $title }} ({{ $code }})</option>
                                    @endforeach
                                </select>
                            </td>

                            {{-- COURSE 2ND CHOICE DROPDOWN --}}
                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $idx }}][course_choice_2]">
                                    <option value="">— N/A (None) —</option>
                                    @foreach($courses as $code => $title)
                                        <option value="{{ $code }}">{{ $title }} ({{ $code }})</option>
                                    @endforeach
                                </select>
                            </td>

                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $idx }}][sex]">
                                    <option value="">—</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $idx }}][special_group]">
                                    @foreach($specialGroupOptions as $opt)
                                        <option value="{{ $opt }}" @selected($opt === 'N/A')>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm border-0 cell-select"
                                        name="rows[{{ $idx }}][cmfl]">
                                    @foreach($cmflOptions as $opt)
                                        <option value="{{ $opt }}" @selected($opt === 'N/A')>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="75" max="100"
                                       class="form-control form-control-sm border-0 px-1 cell-input"
                                       name="rows[{{ $idx }}][gwa]"
                                       placeholder="" autocomplete="off">
                            </td>
                        </tr>
                        @endfor
                    @endunless
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">
                        @if($isLocked)
                            <i class="bi bi-lock-fill me-1"></i> Cycle is completed / archived. Encoding new rows is disabled.
                        @else
                            <i class="bi bi-info-circle me-1"></i> Empty rows are ignored. Tab through cells to navigate.
                        @endif
                    </span>
                    @unless($isLocked)
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-rows">
                            <i class="bi bi-plus-lg me-1"></i> Add 10 More Rows
                        </button>
                    @endunless
                </div>
                @unless($isLocked)
                    <div class="d-flex gap-2 align-items-center">
                        <span id="save-status-indicator" class="small text-muted d-none"></span>
                        <button class="btn btn-primary btn-sm" id="btn-save-grid-bottom" form="encoding-form" type="submit">
                            <i class="bi bi-save me-1"></i> Save Encoded Rows
                        </button>
                    </div>
                @endunless
            </div>
        </form>
    </div>
</div>

{{-- ── JAVASCRIPT GRID CONTROLLER ── --}}
@push('scripts')
<script>
function loadBatchGroup(value) {
    if (!value) return;
    const url = new URL(window.location.href);
    url.searchParams.set('batch_group', value);
    window.location.href = url.toString();
}

(function () {
    const grid = document.getElementById('encoding-grid');
    const form = document.getElementById('encoding-form');
    const saveBtnTop = document.getElementById('btn-save-grid');
    const saveBtnBottom = document.getElementById('btn-save-grid-bottom');
    const addRowsBtn = document.getElementById('btn-add-rows');
    const statusIndicator = document.getElementById('save-status-indicator');

    if (!grid || !form) return;

    // Course options cache for dynamic row creation
    const courses = @json($courses);
    const specialGroupOpts = ['N/A', '4Ps', 'OSY', 'IP', 'PWD', 'SP'];
    const cmflOpts = ['N/A', '10,000 below', '10,001 to 20,000', '20,001 to 30,000', '30,001 to 50,000', '50,001 and above'];

    // ── Row Focus Highlighting & Keyboard Navigation ──
    function bindRowEvents(row) {
        row.querySelectorAll('input, select').forEach(function (el) {
            el.addEventListener('focus', function () {
                row.classList.add('table-primary');
            });
            el.addEventListener('blur', function () {
                row.classList.remove('table-primary');
            });
            el.addEventListener('keydown', function (e) {
                // Enter key moves down to same column next row
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const currentCellIndex = Array.from(row.children).indexOf(this.closest('td'));
                    const nextRow = row.nextElementSibling;
                    if (nextRow) {
                        const targetCell = nextRow.children[currentCellIndex];
                        const targetInput = targetCell ? targetCell.querySelector('input, select') : null;
                        if (targetInput) targetInput.focus();
                    }
                }
            });
        });
    }

    grid.querySelectorAll('#grid-body tr').forEach(bindRowEvents);

    // ── Add Dynamic Rows ──
    if (addRowsBtn) {
        addRowsBtn.addEventListener('click', function () {
            const tbody = document.getElementById('grid-body');
            const currentTotal = tbody.querySelectorAll('tr').length;
            const fragment = document.createDocumentFragment();

            for (let k = 0; k < 10; k++) {
                const idx = currentTotal + k;
                const rowNum = idx + 1;
                const tr = document.createElement('tr');
                tr.setAttribute('data-row', rowNum);
                tr.className = 'empty-row';

                let c1OptionsHtml = '<option value="">— Select —</option>';
                let c2OptionsHtml = '<option value="">— N/A (None) —</option>';
                for (const [code, title] of Object.entries(courses)) {
                    c1OptionsHtml += `<option value="${code}">${title} (${code})</option>`;
                    c2OptionsHtml += `<option value="${code}">${title} (${code})</option>`;
                }

                let spOptionsHtml = '';
                specialGroupOpts.forEach(opt => {
                    spOptionsHtml += `<option value="${opt}" ${opt === 'N/A' ? 'selected' : ''}>${opt}</option>`;
                });

                let cmflOptionsHtml = '';
                cmflOpts.forEach(opt => {
                    cmflOptionsHtml += `<option value="${opt}" ${opt === 'N/A' ? 'selected' : ''}>${opt}</option>`;
                });

                tr.innerHTML = `
                    <td class="text-center text-muted small align-middle row-number">${rowNum}</td>
                    <td>
                        <input type="hidden" name="rows[${idx}][id]" value="" class="cell-id">
                        <input class="form-control form-control-sm border-0 px-1 cell-input"
                               name="rows[${idx}][last_name]" placeholder="Last name" autocomplete="off">
                    </td>
                    <td>
                        <input class="form-control form-control-sm border-0 px-1 cell-input"
                               name="rows[${idx}][first_name]" placeholder="Given name" autocomplete="off">
                    </td>
                    <td>
                        <input class="form-control form-control-sm border-0 px-1 cell-input"
                               name="rows[${idx}][middle_name]" placeholder="Middle name" autocomplete="off">
                    </td>
                    <td>
                        <select class="form-select form-select-sm border-0 cell-select"
                                name="rows[${idx}][course_choice_1]">
                            ${c1OptionsHtml}
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm border-0 cell-select"
                                name="rows[${idx}][course_choice_2]">
                            ${c2OptionsHtml}
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm border-0 cell-select"
                                name="rows[${idx}][sex]">
                            <option value="">—</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm border-0 cell-select"
                                name="rows[${idx}][special_group]">
                            ${spOptionsHtml}
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm border-0 cell-select"
                                name="rows[${idx}][cmfl]">
                            ${cmflOptionsHtml}
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="75" max="100"
                               class="form-control form-control-sm border-0 px-1 cell-input"
                               name="rows[${idx}][gwa]" placeholder="" autocomplete="off">
                    </td>
                `;

                bindRowEvents(tr);
                fragment.appendChild(tr);
            }

            tbody.appendChild(fragment);
            showToast('Added 10 more rows to the encoding sheet.', 'info');
        });
    }

    // ── AJAX Batch Save Controller ──
    async function executeBatchSave() {
        const buttons = [saveBtnTop, saveBtnBottom].filter(Boolean);
        buttons.forEach(b => {
            b.disabled = true;
            b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving…';
        });

        if (statusIndicator) {
            statusIndicator.className = 'small text-muted';
            statusIndicator.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> Saving changes…';
        }

        try {
            const formData = new FormData(form);
            const saveUrl = form.action || "{{ route('admin.admission.applicants.store-batch-encoded') }}";

            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                                    form.querySelector('input[name="_token"]')?.value || ''
                },
                body: formData
            });

            if (!response.ok) {
                const errData = await response.json().catch(() => ({}));
                throw new Error(errData.message || 'Server error ' + response.status);
            }

            const data = await response.json();
            showToast(data.message || 'Rows successfully saved.', 'success');

            if (statusIndicator) {
                statusIndicator.className = 'small text-success fw-semibold';
                statusIndicator.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + (data.message || 'Saved successfully');
                setTimeout(() => { statusIndicator.classList.add('d-none'); }, 4000);
            }

            // Sync generated applicant IDs to hidden inputs
            if (data.saved_ids && Array.isArray(data.saved_ids) && data.saved_ids.length > 0) {
                let idIdx = 0;
                grid.querySelectorAll('#grid-body tr').forEach(function (tr) {
                    const ln = tr.querySelector('input[name$="[last_name]"]')?.value.trim();
                    const fn = tr.querySelector('input[name$="[first_name]"]')?.value.trim();
                    const idInput = tr.querySelector('.cell-id');
                    if (ln && fn && idInput && !idInput.value && idIdx < data.saved_ids.length) {
                        idInput.value = data.saved_ids[idIdx++];
                    }
                });
            }
        } catch (error) {
            console.error('Batch save error:', error);
            showToast('Save error: ' + error.message, 'danger');
            if (statusIndicator) {
                statusIndicator.className = 'small text-danger fw-semibold';
                statusIndicator.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + error.message;
            }
        } finally {
            buttons.forEach(b => {
                b.disabled = false;
                b.innerHTML = '<i class="bi bi-save me-1"></i> Save Encoded Rows';
            });
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        executeBatchSave();
    });

    function showToast(msg, type) {
        const container = document.getElementById('toast-container') || (() => {
            const c = document.createElement('div');
            c.id = 'toast-container';
            c.className = 'toast-container position-fixed top-0 end-0 p-3';
            c.style.zIndex = '9999';
            document.body.appendChild(c);
            return c;
        })();

        const toast = document.createElement('div');
        toast.className = `toast show text-bg-${type} border-0 shadow-sm mb-2`;
        toast.innerHTML = `
            <div class="toast-body d-flex justify-content-between align-items-center">
                <span>${msg}</span>
                <button class="btn-close btn-close-white ms-2" onclick="this.closest('.toast').remove()"></button>
            </div>
        `;
        container.prepend(toast);
        setTimeout(() => { try { toast.remove(); } catch (e) {} }, 4500);
    }
})();
</script>
@endpush

@endsection
