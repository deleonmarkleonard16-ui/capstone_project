@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-12 col-md-9 col-lg-7 col-xl-6">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            {{-- Header with PSU branding --}}
            <div class="bg-primary bg-gradient text-white p-4 text-center position-relative">
                <div class="d-inline-flex align-items-center justify-content-center bg-white bg-opacity-20 rounded-circle p-2 mb-2" style="width: 56px; height: 56px;">
                    <i class="bi bi-shield-check fs-2 text-warning"></i>
                </div>
                <h2 class="h5 text-uppercase fw-bold letter-spacing-1 mb-1 text-warning">
                    PSU-CAT Venue Attendance Verification
                </h2>
                <p class="text-white-50 small mb-0">
                    Pangasinan State University – San Carlos Campus
                </p>
            </div>

            <div class="card-body p-4 p-md-5">
                {{-- Session Details Banner --}}
                <div class="bg-light border rounded-3 p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary px-2 py-1">
                            <i class="bi bi-calendar3 me-1"></i> {{ $session->session_name }}
                        </span>
                        @if ($session->status === 'In-Progress')
                            <span class="badge bg-warning text-dark"><i class="bi bi-play-circle-fill me-1"></i> Check-in Active</span>
                        @elseif ($session->status === 'Completed')
                            <span class="badge bg-secondary"><i class="bi bi-check-circle me-1"></i> Completed</span>
                        @else
                            <span class="badge bg-info text-dark"><i class="bi bi-clock me-1"></i> Scheduled</span>
                        @endif
                    </div>

                    <div class="row g-2 small text-secondary">
                        <div class="col-sm-6">
                            <div class="text-muted text-uppercase" style="font-size: 11px;">Testing Venue / Room</div>
                            <div class="fw-bold text-dark">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                {{ $session->room ?: 'Main Testing Hall' }}
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted text-uppercase" style="font-size: 11px;">Start Date &amp; Time</div>
                            <div class="fw-bold text-dark">
                                <i class="bi bi-clock-fill text-primary me-1"></i>
                                {{ optional($session->start_time)->format('M d, Y · h:i A') ?: 'TBA' }}
                            </div>
                        </div>
                        <div class="col-sm-12 mt-2 pt-2 border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size: 11px;">Assigned Examinee Range:</span>
                                <span class="badge bg-white text-primary border font-monospace">
                                    #{{ $session->start_number }} – #{{ $session->end_number }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Status Alert --}}
                @if ($session->isCompleted())
                    <div class="alert alert-secondary d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-info-circle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div class="small">
                            This test session has ended. Check-in is no longer accepting submissions.
                        </div>
                    </div>
                @elseif (!$isCheckinOpen)
                    <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-info-circle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div class="small">
                            Check-in verification is open. Please enter your name below to enter the examination kiosk.
                        </div>
                    </div>
                @endif

                {{-- Validation Errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-start mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0 mt-1"></i>
                        <div>
                            <div class="fw-bold small mb-1">Verification Unsuccessful</div>
                            <ul class="mb-0 ps-3 small">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                {{-- Attendance Verification Form --}}
                <form method="POST" action="{{ route('admission.checkin.verify', $session->qr_token) }}" class="needs-validation">
                    @csrf

                    <div class="mb-3">
                        <label for="first_name" class="form-label fw-bold text-dark small mb-1">
                            First Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text"
                                   class="form-control @error('first_name') is-invalid @enderror"
                                   id="first_name"
                                   name="first_name"
                                   value="{{ old('first_name') }}"
                                   placeholder="e.g. Maria Clara"
                                   required
                                   autofocus>
                        </div>
                        <div class="form-text text-muted" style="font-size: 11px;">
                            Enter your given name(s) as registered on your application.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="middle_name" class="form-label fw-bold text-dark small mb-1">
                            Middle Name <span class="badge bg-light text-secondary border fw-normal">Optional</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary">
                                <i class="bi bi-person-lines-fill"></i>
                            </span>
                            <input type="text"
                                   class="form-control @error('middle_name') is-invalid @enderror"
                                   id="middle_name"
                                   name="middle_name"
                                   value="{{ old('middle_name') }}"
                                   placeholder="e.g. Santos">
                        </div>
                        <div class="form-text text-muted" style="font-size: 11px;">
                            Leave blank if you do not have a middle name.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="last_name" class="form-label fw-bold text-dark small mb-1">
                            Last Name / Surname <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary">
                                <i class="bi bi-person-fill"></i>
                            </span>
                            <input type="text"
                                   class="form-control @error('last_name') is-invalid @enderror"
                                   id="last_name"
                                   name="last_name"
                                   value="{{ old('last_name') }}"
                                   placeholder="e.g. Dela Cruz"
                                   required>
                        </div>
                        <div class="form-text text-muted" style="font-size: 11px;">
                            Enter your family name / surname.
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit"
                                class="btn btn-primary btn-lg fw-bold py-3 shadow-sm"
                                @disabled($session->isCompleted())>
                            <i class="bi bi-box-arrow-in-right me-2"></i> Verify Attendance &amp; Take Exam
                        </button>
                    </div>

                    <div class="text-center text-muted small">
                        <i class="bi bi-lock-fill text-warning me-1"></i>
                        Upon successful match, you will be redirected to the secure digital lockdown examination page.
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
