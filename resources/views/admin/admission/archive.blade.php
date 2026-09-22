@extends('layouts.app')
@section('content')
<h1 class="h3 mb-2">Admission Archive</h1>
<p class="text-muted">Archived admission cycles and their applicant records.</p>
<div class="card page-card p-4">
    <table class="table"><thead><tr><th>Cycle</th><th>Applicants</th><th>Records</th></tr></thead><tbody>
        @forelse($cycles as $cycle)
        <tr><td>{{ $cycle->display_name }}</td><td>{{ $cycle->applicants_count }}</td>
            <td><a href="{{ route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary']) }}">View archived records</a></td></tr>
        @empty
        <tr><td colspan="3">No archived admission cycles.</td></tr>
        @endforelse
    </tbody></table>
    {{ $cycles->links() }}
</div>
@endsection
