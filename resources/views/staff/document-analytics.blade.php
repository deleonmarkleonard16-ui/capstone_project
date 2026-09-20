@extends('layouts.app')
@section('content')
@php($moduleKey = $module)
@php($mode = 'analytics')
<h1 class="h3">{{ \App\Http\Controllers\DocumentRequestController::MODULES[$module] }} Analytics</h1>
<p class="text-muted">Request status and completion metrics for this document module only.</p>
@include('staff.partials.document-tabs')
@include('staff.partials.course-filter')
<div class="card card-body mb-4"><h2 class="h5">Requests by program</h2><div class="table-responsive"><table class="table"><thead><tr><th>Program</th><th>Requests</th></tr></thead><tbody>@forelse($courses as $code => $total)<tr><th>{{ \App\Support\CourseCatalog::label($code) }}</th><td>{{ $total }}</td></tr>@empty<tr><td colspan="2">No requests for the selected program.</td></tr>@endforelse</tbody></table></div></div>
<div class="row g-3 mb-4">@foreach(['pending'=>'Awaiting receipt','proof_review'=>'Receipt review','ready'=>'Ready for pickup','completed'=>'Claimed / Completed'] as $status=>$label)<div class="col-6 col-lg-3"><div class="card card-body h-100"><span class="text-muted">{{ $label }}</span><strong class="h2 mb-0">{{ $counts[$status] ?? 0 }}</strong></div></div>@endforeach</div>
<div class="card card-body mb-4"><h2 class="h5">Status distribution</h2><div class="table-responsive"><table class="table"><thead><tr><th>Status</th><th>Requests</th><th>Share</th></tr></thead><tbody>@forelse($counts as $status=>$total)<tr><th>{{ ucfirst(str_replace('_',' ', $status)) }}</th><td>{{ $total }}</td><td><div class="progress" role="progressbar" aria-label="{{ $status }} requests" aria-valuenow="{{ $total }}" aria-valuemin="0" aria-valuemax="{{ max(1, $counts->sum()) }}"><div class="progress-bar" style="width:{{ round(100*$total/max(1,$counts->sum())) }}%"></div></div></td></tr>@empty<tr><td colspan="3">No requests yet.</td></tr>@endforelse</tbody></table></div></div>
<div class="card card-body mb-4"><h2 class="h5">Monthly requests</h2><div class="table-responsive"><table class="table"><thead><tr><th>Month</th><th>Requests</th><th>Distribution</th></tr></thead><tbody>@forelse($monthly as $month=>$total)<tr><th>{{ $month }}</th><td>{{ $total }}</td><td><div class="progress" role="progressbar" aria-label="{{ $month }} requests" aria-valuenow="{{ $total }}" aria-valuemin="0" aria-valuemax="{{ max(1, $monthly->max()) }}"><div class="progress-bar" style="width:{{ round(100*$total/max(1,$monthly->max())) }}%"></div></div></td></tr>@empty<tr><td colspan="3">No requests yet.</td></tr>@endforelse</tbody></table></div></div>
<div class="alert alert-warning"><strong>Completion alert:</strong> {{ $counts['ready'] ?? 0 }} document(s) are ready for pickup.</div>
@endsection
