@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Guidance Testing</h1>
            <p class="text-muted mb-0">Psychological, personality, and career assessments for the guidance office.</p>
        </div>
        <span class="module-chip bg-warning-subtle text-warning-emphasis">UI Only For Now</span>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body p-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <h2 class="section-title mb-3">Digital Management System for Guidance Testing and Admission at Pangasinan State University - San Carlos Campus</h2>
                    <p class="text-muted mb-3">This page outlines the planned guidance testing area for admins and staff. The modules below are intentionally visible but not yet active so you can refine the capstone scope before implementation.</p>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="rounded-4 border p-3 bg-light h-100">
                                <div class="fw-semibold">Psychological Assessment</div>
                                <div class="small text-muted">Initial mental health screening tools</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="rounded-4 border p-3 bg-light h-100">
                                <div class="fw-semibold">Personality Test</div>
                                <div class="small text-muted">Field study and request-based testing</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="rounded-4 border p-3 bg-light h-100">
                                <div class="fw-semibold">Career Test</div>
                                <div class="small text-muted">Support for shifters and guidance requests</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="brand-panel rounded-4 p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" style="width: 72px; height: 72px; object-fit: contain; background: transparent;">
                            <div>
                                <div class="fw-bold fs-5">Planned Testing Workspace</div>
                                <div class="small text-white-50">Modules remain disabled until your final tools and process are confirmed.</div>
                            </div>
                        </div>
                        <ul class="mb-0 small text-white-50">
                            <li>Assessment setup and activation controls</li>
                            <li>Assigned student/test request management</li>
                            <li>Result review and guidance office follow-up</li>
                            <li>Reporting and printable summaries</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card page-card mb-4">
        <div class="card-body p-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <h2 class="h4 section-title mb-2">Emotional Assessment Recommendation Outcomes</h2>
                    <p class="text-muted mb-0">These recommendation labels are now reflected in the admin-side concept UI for the Depression, Anxiety, and Stress Scale workflow.</p>
                </div>
                <div class="col-lg-5">
                    <div class="d-flex flex-column gap-2">
                        @foreach ($recommendations as $recommendation)
                            <div class="rounded-4 border bg-light px-3 py-3 fw-semibold">{{ $recommendation }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    <div class="card page-card mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h5 section-title mb-1">Completed Student Assessment Submissions</h2>
                    <p class="text-muted small mb-0">Online questionnaires taken by students via their tracking portal.</p>
                </div>
                <span class="badge bg-primary fs-6">{{ count($recentSubmissions) }} Submissions</span>
            </div>

            @if(count($recentSubmissions) > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student Name</th>
                                <th>Reference</th>
                                <th>Assessment</th>
                                <th>Completed</th>
                                <th>Summary Result</th>
                                <th>Counselor Notes</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentSubmissions as $sub)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ strtoupper(trim(($sub->serviceRequest->first_name ?? '').' '.($sub->serviceRequest->last_name ?? ''))) }}</div>
                                        <small class="text-muted">{{ $sub->serviceRequest->course ?? 'N/A' }}</small>
                                    </td>
                                    <td><code>{{ $sub->serviceRequest->reference ?? '—' }}</code></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ ['dass21'=>'Psychological Assessment','phq9'=>'Psychological Assessment','gad7'=>'Psychological Assessment','bfpi'=>'BFPI','career'=>'Career Test'][$sub->test_type] ?? strtoupper($sub->test_type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>{{ $sub->completed_at?->timezone('Asia/Manila')->format('M d, Y h:i A') }}</small>
                                    </td>
                                    <td>
                                        @if($sub->test_type === 'dass21')
                                            @php $interp = $sub->interpretation ?? []; @endphp
                                            <span class="badge bg-light text-dark border">Dep: {{ $interp['depression'] ?? '—' }}</span>
                                            <span class="badge bg-light text-dark border">Anx: {{ $interp['anxiety'] ?? '—' }}</span>
                                            <span class="badge bg-light text-dark border">Str: {{ $interp['stress'] ?? '—' }}</span>
                                        @elseif(isset($sub->interpretation['severity']))
                                            <span class="badge bg-info-subtle text-info-emphasis">{{ $sub->interpretation['severity'] }}</span>
                                        @elseif(isset($sub->interpretation['overall_level']))
                                            <span class="badge bg-info-subtle text-info-emphasis">{{ $sub->interpretation['overall_level'] }}</span>
                                        @elseif(isset($sub->interpretation['top_traits']))
                                            <small class="fw-semibold text-primary">{{ $sub->interpretation['top_traits'] }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($sub->counselor_notes)
                                            <span class="badge bg-success-subtle text-success">Notes added</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning-emphasis">Pending notes</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route(auth()->user()->role.'.guidance.submission.show', $sub) }}" class="btn btn-sm btn-outline-primary">
                                            Review & Notes
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4 text-muted">
                    <p class="mb-1">No completed test submissions yet.</p>
                    <small>When students complete an online assessment from their verified pass, results appear here for counseling review.</small>
                </div>
            @endif
        </div>
    </div>

    <div class="mb-4">
        <h2 id="psychological-test" class="h4 section-title mb-3">Psychological Test</h2>
        <div class="row g-4">
            @foreach ($psychologicalTests as $test)
                <div class="col-lg-4">
                    <div class="card module-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h3 class="h5 mb-1">{{ $test['name'] }}</h3>
                                    <div class="text-muted small">{{ $test['subtitle'] }}</div>
                                </div>
                                <span class="module-chip text-bg-{{ $test['color'] }}">{{ $test['status'] }}</span>
                            </div>
                            <p class="text-muted flex-grow-1">{{ $test['description'] }}</p>
                            <button type="button" class="btn btn-outline-secondary w-100 mt-3" disabled>Module Not Yet Activated</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <h2 class="h4 section-title mb-3">Specialized Testing</h2>
        <div class="row g-4">
            @foreach ($specializedTests as $test)
                <div class="col-lg-6" id="{{ \Illuminate\Support\Str::slug($test['name']) }}">
                    <div class="card module-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h3 class="h5 mb-1">{{ $test['name'] }}</h3>
                                    <div class="text-muted small">{{ $test['subtitle'] }}</div>
                                </div>
                                <span class="module-chip text-bg-{{ $test['color'] }}">{{ $test['status'] }}</span>
                            </div>
                            <p class="text-muted flex-grow-1">{{ $test['description'] }}</p>
                            <button type="button" class="btn btn-outline-secondary w-100 mt-3" disabled>Module Not Yet Activated</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
