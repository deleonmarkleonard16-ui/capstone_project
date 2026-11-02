@php
    $pdfRole = auth()->user()->role;
    $isCareerReport = $appointment->test_category === 'career' || $appointment->test_type === 'career';
    $pdfUrl = route($isCareerReport ? $pdfRole.'.guidance-appointments.career-report' : $pdfRole.'.guidance.report.download', [
        'appointment' => $appointment->getKey(),
        'format' => 'pdf',
    ]);
@endphp
@if($appointment->response && in_array($appointment->status, ['Completed', 'Under review'], true))
    <a class="btn btn-sm btn-outline-primary" href="{{ route($pdfRole.'.guidance-appointments.show-results', $appointment) }}"><i class="bi bi-file-earmark-text me-1"></i>Review Result</a>
    <a class="btn btn-sm btn-outline-success" href="{{ $pdfUrl }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>View / Print PDF</a>
@else
    <span class="text-muted small">No submitted assessment</span>
@endif
