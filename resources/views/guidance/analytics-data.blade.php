<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card card-body"><span>Completed submissions</span><strong class="h2">{{ $totals['responses'] }}</strong></div></div>
    <div class="col-md-6"><div class="card card-body border-danger"><span>Submissions requiring priority counselor review</span><strong class="h2 text-danger">{{ $totals['flagged'] }}</strong></div></div>
</div>
<div class="card card-body mb-4"><h2 class="h5">Completed scores by original section</h2><div class="table-responsive"><table class="table"><thead><tr><th>Batch / section</th><th>Program</th><th>Completed</th><th>Makeup completions</th></tr></thead><tbody>@forelse($sectionTotals as $section)<tr><th>{{ $section['batch'] }} / {{ $section['section'] }}</th><td>{{ \App\Support\CourseCatalog::label($section['course']) }}</td><td>{{ $section['completed'] }}</td><td>{{ $section['makeup'] }}</td></tr>@empty<tr><td colspan="4">No completed batch assessments yet.</td></tr>@endforelse</tbody></table></div></div>
@if(count($distributions) > 0)
<div class="row g-3 mb-4">
@foreach($distributions as $series)
<div class="col-lg-6"><div class="card card-body h-100"><h2 class="h5">{{ $series['label'] }}</h2>
    <table class="table table-sm"><thead><tr><th>Interpretation</th><th>Count</th><th>Distribution</th></tr></thead><tbody>
    @foreach($series['counts'] as $severity => $count)
    @php($percent = array_sum($series['counts']) ? round(100 * $count / array_sum($series['counts']), 1) : 0)
    <tr><th scope="row">{{ $severity }}</th><td>{{ $count }}</td><td class="w-50"><div class="progress" role="progressbar" aria-label="{{ $severity }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar {{ in_array($severity, ['Severe', 'Extremely Severe']) ? 'bg-danger' : '' }}" style="width:{{ $percent }}%">{{ $percent }}%</div></div></td></tr>
    @endforeach
    </tbody></table>
</div></div>
@endforeach
</div>
@else
<div class="alert alert-info mb-4" role="alert">
    <h2 class="h5 alert-heading"><i class="bi bi-bar-chart-line me-2"></i>No scoring dimensions configured for this module yet</h2>
    <p class="mb-1">Interpretation charts will appear here once the instrument is configured for <strong>{{ $module['label'] ?? 'this module' }}</strong>.</p>
    <p class="mb-0 small text-muted">Tests in this module: <strong>{{ implode(', ', $module['tests'] ?? []) }}</strong></p>
</div>
@endif
<div class="card border-danger"><div class="card-body"><h2 class="h4">Counselor Red-Flag Alerts</h2>
@if($moduleKey === 'psychological')
<p>Severe or Extremely Severe scores require prompt counselor review. These screening results are not diagnoses. Archived records remain visible here.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Student / ID</th><th>Reference</th><th>Flagged scales</th><th>Action</th></tr></thead><tbody>
@forelse($flags as $response)<tr><td>{{ $response->applicant->full_name }}<br>{{ $response->appointment->serviceRequest?->student_number }}</td><td>{{ $response->appointment->serviceRequest?->reference ?? $response->appointment->request_code }}</td>
<td>@foreach($response->testSummaries() as $test => $summary)@foreach($summary['interpretation'] ?? [] as $scale => $severity)@if(in_array($severity, ['Severe', 'Extremely Severe'], true))<div><strong>{{ \App\Services\GuidanceTestScoringService::LABELS[$test] ?? $test }} {{ $scale === 'severity' ? '' : ucfirst($scale) }}:</strong> {{ $severity }}</div>@endif @endforeach @endforeach</td>
<td><a class="btn btn-sm btn-outline-danger" href="{{ route(auth()->user()->role.'.guidance-appointments.show-results', $response->appointment) }}">Review Student Submission</a></td></tr>
@empty<tr><td colspan="4">No Severe or Extremely Severe results recorded.</td></tr>@endforelse
</tbody></table></div>{{ $flags->links('pagination::bootstrap-5') }}
@else
<p class="text-muted mb-0">No clinical red-flag thresholds are defined for this test category.</p>
@endif
</div></div>
