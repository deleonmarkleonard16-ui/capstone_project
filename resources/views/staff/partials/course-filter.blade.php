@php
    $batchFilters = request()->routeIs(auth()->user()->role.'.*.batches') || request()->routeIs(auth()->user()->role.'.guidance-batches.*');
    $analyticsFilters = request()->routeIs(auth()->user()->role.'.*.analytics');
    $statuses = $batchFilters ? ['Pending Registration', 'In-Progress', 'Completed'] : (in_array($moduleKey ?? null, ['good-moral', 'exit-form'], true)
        ? ['pending', 'proof_review', 'ready', 'completed', 'void', 'declined']
        : array_unique(array_merge(['pending', 'approved', 'proof_review', 'processing', 'ready', 'scheduled', 'completed', 'declined', 'cancelled', 'void'], \App\Models\GuidanceAppointment::STATUSES)));
@endphp
<form method="get" action="{{ url()->current() }}" class="d-flex flex-wrap align-items-end gap-2 mb-3" aria-label="Search and filter records" data-table-filters>
    @if(request()->boolean('archived'))<input type="hidden" name="archived" value="1">@endif
    @unless($analyticsFilters)<div><label class="form-label" for="table-search">Search student, ID, reference or section</label><input class="form-control" id="table-search" name="q" value="{{ request('q') }}" maxlength="100" autocomplete="off"></div>@endunless
    <div><label class="form-label" for="course-filter">Course / program</label>
        <select class="form-select" id="course-filter" name="course">
            <option value="">All programs</option>
            @foreach(\App\Support\CourseCatalog::allOptions() as $code => $title)
                <option value="{{ $code }}" @selected(request('course') === $code)>{{ $title }} ({{ $code }})</option>
            @endforeach
        </select>
    </div>
    @unless($analyticsFilters)
    <div><label class="form-label" for="table-status">Status</label><select class="form-select" id="table-status" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
    <div><label class="form-label" for="table-from">From</label><input class="form-control" id="table-from" type="date" name="date_from" value="{{ request('date_from') }}"></div>
    <div><label class="form-label" for="table-to">To</label><input class="form-control" id="table-to" type="date" name="date_to" value="{{ request('date_to') }}"></div>
    @endunless
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-outline-secondary" href="{{ url()->current() }}{{ request()->boolean('archived') ? '?archived=1' : '' }}">Clear</a>
</form>
<script>
document.querySelectorAll('[data-table-filters]').forEach(form => {
    let timer;
    form.querySelectorAll('input, select').forEach(field => {
        const event = field.name === 'q' ? 'input' : 'change';
        field.addEventListener(event, () => { clearTimeout(timer); timer = setTimeout(() => form.requestSubmit(), event === 'input' ? 450 : 0); });
    });
});
</script>
