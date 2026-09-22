@extends('layouts.app')
@section('content')
<h1 class="h3 mb-3">Admission Analytics</h1>
<form method="get" class="d-flex gap-2 mb-4">
    <label for="analytics-cycle" class="form-label align-self-center mb-0">Admission cycle</label>
    <select id="analytics-cycle" name="cycle_id" class="form-select w-auto">
        @foreach($cycles as $option)
        <option value="{{ $option->id }}" @selected($cycle?->id === $option->id)>{{ $option->display_name }}</option>
        @endforeach
    </select>
    <button class="btn btn-primary">View</button>
</form>
@if($cycle)
<div class="card page-card p-4">
    <h2 class="h5">{{ $cycle->display_name }}</h2>
    <p>Total applicants: <strong>{{ $statuses->sum() }}</strong></p>
    <table class="table"><thead><tr><th>Qualification status</th><th>Applicants</th></tr></thead><tbody>
        @forelse($statuses as $status => $count)
        <tr><td>{{ $status ?: 'Pending' }}</td><td>{{ $count }}</td></tr>
        @empty
        <tr><td colspan="2">No applicants in this cycle.</td></tr>
        @endforelse
    </tbody></table>
    <a href="{{ route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary']) }}">View detailed admission report</a>
</div>
@else
<p class="text-muted">No admission cycles available.</p>
@endif
@endsection
