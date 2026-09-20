@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Completed Guidance Assessments</h1>
        <p class="text-muted mb-0">Review completed student test results and psychometric summaries.</p>
    </div>
    <div>
        <a href="{{ route(auth()->user()->role.'.guidance.index') }}" class="btn btn-outline-primary btn-sm">Request Queue</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Applicant</th>
                        <th>Course / Student ID</th>
                        <th>Assessment</th>
                        <th>Completed At</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($appointments as $appointment)
                        <tr>
                            <td>
                                <strong>{{ $appointment->applicant->full_name }}</strong>
                                <div class="small text-muted">{{ $appointment->serviceRequest?->reference ?? $appointment->request_code }}</div>
                            </td>
                            <td>
                                <div>{{ $appointment->serviceRequest?->course ?: 'N/A' }}</div>
                                <div class="small text-muted">{{ $appointment->serviceRequest?->student_number ?: 'Alumni' }}</div>
                            </td>
                            <td>
                                <span class="badge text-bg-primary">{{ $appointment->testLabel() }}</span>
                            </td>
                            <td>
                                <div>{{ $appointment->response?->created_at?->timezone('Asia/Manila')->format('M d, Y') }}</div>
                                <div class="small text-muted">{{ $appointment->response?->created_at?->timezone('Asia/Manila')->format('g:i A') }}</div>
                            </td>
                            <td class="text-end">
                                <a href="{{ route(auth()->user()->role.'.guidance-appointments.show-results', $appointment) }}" class="btn btn-sm btn-outline-success">
                                    View Psychometric Results
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                No completed guidance assessments yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($appointments->hasPages())
        <div class="card-footer bg-white">
            {{ $appointments->links() }}
        </div>
    @endif
</div>
@endsection
