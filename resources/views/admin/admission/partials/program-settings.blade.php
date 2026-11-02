<details class="card page-card shadow-sm mb-3" @if($errors->any()) open @endif>
    <summary class="p-3 fw-semibold">Program Settings <span class="small text-muted fw-normal ms-2">Enrollment seats and interview selection · Click to expand / collapse</span></summary>
    <div class="card-body pt-0">
        <p class="small text-muted">Enrollment seats and interview limits are separate settings. Interview selection still follows the applicable exam eligibility rules.</p>
        <form id="admission-program-settings" method="post" action="{{ route('admin.admission.program-settings.save') }}">
            @csrf
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Program</th><th>Enrollment Seats</th><th>Interview Selection</th></tr></thead>
                    <tbody>
                    @foreach($reportPrograms as $code)
                        @php
                            $savedLimit = $reportCutoffs[$code] ?? 100000;
                            $mode = old('settings.'.$code.'.mode', $savedLimit == 0 ? 'none' : ($savedLimit >= 100000 ? 'all' : 'top'));
                        @endphp
                        <tr data-program-row>
                            <th scope="row">{{ $code }}</th>
                            <td><input class="form-control form-control-sm" style="max-width:140px" type="number" min="0" max="100000" required name="settings[{{ $code }}][seats]" value="{{ old('settings.'.$code.'.seats', $courseQuotas[$code] ?? 0) }}" aria-label="{{ $code }} enrollment seats"></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <select class="form-select form-select-sm" style="max-width:220px" name="settings[{{ $code }}][mode]" data-interview-mode aria-label="{{ $code }} interview selection">
                                        <option value="all" @selected($mode === 'all')>All eligible</option>
                                        <option value="top" @selected($mode === 'top')>Top N applicants</option>
                                        <option value="none" @selected($mode === 'none')>None</option>
                                    </select>
                                    <input class="form-control form-control-sm" style="max-width:110px" type="number" min="1" max="99999" name="settings[{{ $code }}][limit]" value="{{ old('settings.'.$code.'.limit', $savedLimit > 0 && $savedLimit < 100000 ? $savedLimit : '') }}" data-interview-limit aria-label="{{ $code }} top applicant count" @if($mode !== 'top') hidden disabled @else required @endif>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-warning-emphasis">Programs with 0 enrollment seats place otherwise eligible applicants on the waitlist.</p>
            @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="cancel-program-settings">Cancel</button>
                <button class="btn btn-primary btn-sm" type="submit">Save Settings</button>
            </div>
        </form>
    </div>
</details>

<section class="card page-card shadow-sm mb-3" aria-labelledby="admission-reports-title">
    <div class="card-body">
        <h2 class="h6" id="admission-reports-title">Admission Reports</h2>
        <p id="admission-settings-warning" class="small text-warning-emphasis" role="status" hidden>Save or cancel your settings changes before generating a report.</p>
        <form id="admission-report-picker" method="get" action="{{ route('admin.admission.reports.psu-cat-qualifiers') }}" target="_blank" rel="noopener">
            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label small" for="admission-report-type">Report</label>
                    <select id="admission-report-type" class="form-select">
                        @foreach(['psu-cat-qualifiers' => 'PSU-CAT Roster', 'interview-qualifiers' => 'Interview Qualifiers', 'interview-non-qualifiers' => 'Interview Non-qualifiers', 'final-enrollment-qualified' => 'Enrollment Qualifiers', 'final-enrollment-waitlisted' => 'Waitlisted Applicants'] as $report => $label)
                            <option value="{{ route('admin.admission.reports.'.$report) }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small" for="admission-report-batch">Batch</label>
                    <select id="admission-report-batch" name="batch_group" class="form-select">
                        <option value="">All batch groups</option>
                        @foreach($batchGroups as $bg)<option value="{{ $bg }}" @selected(request('batch_group') === $bg)>{{ $bg }}</option>@endforeach
                    </select>
                </div>
            </div>
            <button class="btn btn-primary btn-sm" name="format" value="pdf">View / Print PDF</button>
            <button class="btn btn-outline-primary btn-sm" name="format" value="docx">Download DOCX</button>
        </form>
    </div>
</section>

@push('scripts')
<script>
(() => {
    const settings = document.getElementById('admission-program-settings');
    const reports = document.getElementById('admission-report-picker');
    const picker = document.getElementById('admission-report-type');
    const warning = document.getElementById('admission-settings-warning');
    const syncRows = () => settings.querySelectorAll('[data-program-row]').forEach(row => {
        const top = row.querySelector('[data-interview-mode]').value === 'top';
        const limit = row.querySelector('[data-interview-limit]');
        limit.hidden = !top;
        limit.disabled = !top;
        limit.required = top;
    });
    const snapshot = () => JSON.stringify([...new FormData(settings)].filter(([key]) => key !== '_token'));
    syncRows();
    const original = snapshot();
    const update = () => {
        syncRows();
        const dirty = snapshot() !== original || {{ $errors->any() ? 'true' : 'false' }};
        warning.hidden = !dirty;
        reports.querySelectorAll('button').forEach(button => button.disabled = dirty);
    };
    settings.addEventListener('input', update);
    settings.addEventListener('change', update);
    document.getElementById('cancel-program-settings').addEventListener('click', () => window.location.reload());
    picker.addEventListener('change', () => reports.action = picker.value);
    reports.addEventListener('submit', event => {
        update();
        if (!warning.hidden) event.preventDefault();
        reports.action = picker.value;
    });
    update();
})();
</script>
@endpush
