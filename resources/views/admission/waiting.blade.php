@php $isGuestView = true; @endphp
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-center py-3 py-md-5">
    <div style="max-width: 580px; width: 100%;">

        {{-- Top Success Banner (matches Image 3) --}}
        <div class="alert alert-success border-0 rounded-3 shadow-sm mb-4 p-3 text-center" style="background-color: #dcfce7; color: #15803d; font-size: 13.5px;">
            You are already checked in. Please continue waiting for the admin to start the test.
        </div>

        {{-- Main Waiting Card (matches Image 3) --}}
        <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-body p-4 p-md-5 text-center">

                {{-- Status Tag --}}
                <div class="text-success text-uppercase fw-bold small mb-2" style="font-size: 12px; letter-spacing: 0.5px;">
                    CHECKED IN
                </div>

                {{-- Title --}}
                <h1 class="h3 fw-bold text-dark mb-2 text-uppercase" style="font-size: 1.45rem; line-height: 1.3;">
                    Welcome, {{ strtoupper(trim($applicant->first_name . ' ' . ($applicant->middle_name ? $applicant->middle_name . ' ' : '') . $applicant->last_name)) }}
                </h1>

                <p class="text-muted small mb-4" style="font-size: 13.5px; line-height: 1.45;">
                    Your attendance has been recorded. Please stay on this page while waiting for the staff to start the exam.
                </p>

                {{-- 3 Metadata Stat Boxes --}}
                <div class="row g-2 text-start mb-4">
                    <div class="col-4">
                        <div class="rounded-3 p-3 h-100" style="background-color: #f8fafc; border: 1px solid #edf2f7;">
                            <div class="text-muted" style="font-size: 11px;">Session</div>
                            <div class="fw-bold text-dark text-truncate" style="font-size: 12.5px;" title="{{ $session->session_name }}">
                                {{ $session->session_name }}
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded-3 p-3 h-100" style="background-color: #f8fafc; border: 1px solid #edf2f7;">
                            <div class="text-muted" style="font-size: 11px;">Room</div>
                            <div class="fw-bold text-dark text-truncate" style="font-size: 12.5px;" title="{{ $session->room ?: 'PSU-SC COVERED COURT' }}">
                                {{ $session->room ?: 'PSU-SC COVERED COURT' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded-3 p-3 h-100" style="background-color: #f8fafc; border: 1px solid #edf2f7;">
                            <div class="text-muted" style="font-size: 11px;">Status</div>
                            <div class="fw-bold text-dark" style="font-size: 12.5px;">
                                {{ $session->status }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Interactive Action or Waiting Alert --}}
                @if ($session->isInProgress() || $session->status === 'In-Progress')
                    <div class="mb-3">
                        <a href="{{ route('admission.take', $applicant->exam_token) }}"
                           class="btn btn-success btn-lg w-100 fw-bold py-3 rounded-3 shadow-sm"
                           style="font-size: 16px;">
                            <i class="bi bi-play-circle-fill me-2"></i> Start Examination Now
                        </a>
                    </div>
                    <div class="text-muted small" style="font-size: 12px;">
                        The proctor has activated this session. Click the button above to begin.
                    </div>
                @else
                    <div class="rounded-3 p-3 text-center small mb-0" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 13px;">
                        Waiting for the staff to click <strong>Start Test</strong>. This page refreshes automatically every 10 seconds.
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Auto-refresh every 10 seconds while session is in Scheduled status
    @if (!$session->isInProgress() && $session->status !== 'In-Progress')
        setTimeout(function() {
            window.location.reload();
        }, 10000);
    @else
        // Automatically redirect to exam if active
        window.location.href = "{{ route('admission.take', $applicant->exam_token) }}";
    @endif
</script>
@endpush
@endsection
