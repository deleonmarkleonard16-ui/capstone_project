@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card page-card shadow-sm">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <p class="text-uppercase fw-semibold text-primary small mb-2">PSU-CAT Applicant Verification</p>
                    <h1 class="h3 mb-1">{{ $sessionRecord->title }}</h1>
                    <p class="text-muted small">Enter your full name exactly as registered in your admission application.</p>
                </div>

                <div class="row g-2 bg-light rounded-3 p-3 mb-4">
                    <div class="col-md-4 small"><strong class="d-block text-muted">Exam Date</strong>{{ $sessionRecord->exam_date?->format('M d, Y') ?? 'TBA' }}</div>
                    <div class="col-md-4 small"><strong class="d-block text-muted">Start Time</strong>{{ $sessionRecord->start_time }}</div>
                    <div class="col-md-4 small"><strong class="d-block text-muted">Room</strong>{{ $sessionRecord->room ?: 'TBA' }}</div>
                </div>

                @unless ($isCheckinOpen)
                    <div class="alert alert-warning">
                        <i class="bi bi-clock me-2"></i>
                        Check-in is not available right now. It opens 30 minutes before the exam starts.
                    </div>
                @else
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Check-in is <strong>open</strong>. After verification you will be directed to the waiting screen.
                    </div>
                @endunless

                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('checkin.verify', $sessionRecord->qr_token) }}" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                               name="last_name" value="{{ old('last_name') }}"
                               placeholder="e.g. Dela Cruz" required autofocus>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                               name="first_name" value="{{ old('first_name') }}"
                               placeholder="e.g. Maria" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Middle Name <span class="text-muted small">(optional)</span></label>
                        <input type="text" class="form-control"
                               name="middle_name" value="{{ old('middle_name') }}"
                               placeholder="e.g. Santos">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100 fw-semibold py-2"
                                @disabled(!$isCheckinOpen)>
                            <i class="bi bi-check2-circle me-1"></i> Verify &amp; Check In
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
