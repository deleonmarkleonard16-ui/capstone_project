@php $isGuestView = true; @endphp
@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-center py-3 py-md-5">
    <div style="max-width: 540px; width: 100%;">

        {{-- Top Error Banner (matches Image 2) --}}
        @if ($errors->any())
            <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4 p-3" style="background-color: #fee2e2; color: #991b1b;">
                <div class="fw-bold small mb-1">Please review the following:</div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Main Applicant Verification Card (matches Image 1 & 2) --}}
        <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-body p-4 p-md-5">
                
                {{-- Header --}}
                <div class="mb-3">
                    <div class="text-primary text-uppercase fw-bold small mb-1" style="font-size: 12px; letter-spacing: 0.5px;">
                        APPLICANT VERIFICATION
                    </div>
                    <h1 class="h3 fw-bold text-dark mb-2" style="font-size: 1.5rem;">
                        {{ $session->session_name }}
                    </h1>
                    <p class="text-muted small mb-0" style="font-size: 13.5px; line-height: 1.45;">
                        Please enter your details exactly as recorded in your admission application to continue.
                    </p>
                </div>

                {{-- Session Details Box (matches Image 1 & 2) --}}
                <div class="rounded-3 p-3 mb-4" style="background-color: #f8fafc; border: 1px solid #edf2f7;">
                    <div class="row g-2 text-dark" style="font-size: 13px;">
                        <div class="col-6">
                            <span class="fw-bold text-dark">Exam Date:</span>
                            <span class="text-secondary ms-1">{{ optional($session->start_time)->format('M d, Y') ?: 'TBA' }}</span>
                        </div>
                        <div class="col-6">
                            <span class="fw-bold text-dark">Start Time:</span>
                            <span class="text-secondary ms-1">{{ optional($session->start_time)->format('h:i:s A') ?: '08:00:00' }}</span>
                        </div>
                        <div class="col-6">
                            <span class="fw-bold text-dark">Assigned Range:</span>
                            <span class="text-secondary ms-1">#{{ $session->start_number }} – #{{ $session->end_number }}</span>
                        </div>
                        <div class="col-6">
                            <span class="fw-bold text-dark">Room:</span>
                            <span class="text-secondary ms-1">{{ $session->room ?: 'PSU-SC COVERED COURT' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Status Alert Box (matches Image 1 & 2) --}}
                @if ($session->isCompleted())
                    <div class="rounded-3 p-3 mb-4 small" style="background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                        <i class="bi bi-info-circle me-1"></i>
                        This admission test session has concluded. Check-in is no longer accepting submissions.
                    </div>
                @elseif (!$isCheckinOpen)
                    <div class="rounded-3 p-3 mb-4 small" style="background-color: #fef9c3; color: #854d0e; border: 1px solid #fef08a;">
                        This QR code is not available right now. Applicants can check in on <strong>{{ optional($session->start_time)->format('M d, Y') }}</strong> until exam completion.
                    </div>
                @else
                    <div class="rounded-3 p-3 mb-4 small" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                        Check-in is open. After verification, you can log in right away and stay on the waiting page until the admin starts the exam.
                    </div>
                @endif

                {{-- Verification Form (matches Image 1 & 2) --}}
                <form method="POST" action="{{ route('admission.checkin.verify', $session->qr_token) }}">
                    @csrf

                    <div class="mb-3">
                        <label for="first_name" class="form-label fw-semibold text-dark small mb-1">
                            First Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control rounded-3 py-2 px-3 @error('first_name') is-invalid @enderror"
                               id="first_name"
                               name="first_name"
                               value="{{ old('first_name') }}"
                               placeholder="e.g. Maria Clara"
                               required
                               autofocus
                               style="font-size: 14px;">
                    </div>

                    <div class="mb-3">
                        <label for="middle_name" class="form-label fw-semibold text-dark small mb-1">
                            Middle Name <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <input type="text"
                               class="form-control rounded-3 py-2 px-3 @error('middle_name') is-invalid @enderror"
                               id="middle_name"
                               name="middle_name"
                               value="{{ old('middle_name') }}"
                               placeholder="e.g. Santos"
                               style="font-size: 14px;">
                    </div>

                    <div class="mb-2">
                        <label for="last_name" class="form-label fw-semibold text-dark small mb-1">
                            Last Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control rounded-3 py-2 px-3 @error('last_name') is-invalid @enderror"
                               id="last_name"
                               name="last_name"
                               value="{{ old('last_name') }}"
                               placeholder="e.g. Dela Cruz"
                               required
                               style="font-size: 14px;">
                        <div class="text-muted mt-1" style="font-size: 11.5px;">
                            Enter your name in normal order: first name, middle name, then last name.
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit"
                                class="btn btn-primary w-100 fw-semibold py-2 rounded-3 shadow-xs"
                                style="background-color: #1d4ed8; border-color: #1d4ed8; font-size: 14.5px;"
                                @disabled($session->isCompleted())>
                            Verify and Check In
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
