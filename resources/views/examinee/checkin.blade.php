@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card page-card">
                <div class="card-body p-4">
                    <p class="text-uppercase text-primary fw-semibold mb-2">Applicant Verification</p>
                    <h1 class="h3 mb-2">{{ $sessionRecord->title }}</h1>
                    <p class="text-muted">Please enter your details exactly as recorded in your admission application to continue.</p>

                    <div class="row g-3 bg-light rounded p-3 mb-4">
                        <div class="col-md-4"><strong>Exam Date:</strong> {{ $sessionRecord->exam_date->format('M d, Y') }}</div>
                        <div class="col-md-4"><strong>Start Time:</strong> {{ $sessionRecord->start_time }}</div>
                        <div class="col-md-4"><strong>End Time:</strong> {{ $sessionRecord->end_time }}</div>
                        <div class="col-md-12"><strong>Room:</strong> {{ $sessionRecord->room }}</div>
                    </div>

                    @unless ($isCheckinOpen)
                        <div class="alert alert-warning">
                            This QR code is not available right now. Applicants can check in on {{ $sessionRecord->exam_date->format('M d, Y') }} until {{ $sessionRecord->end_time }}.
                        </div>
                    @else
                        <div class="alert alert-info">
                            Check-in is open. After verification, you will be redirected to the waiting page until staff starts the exam.
                        </div>
                    @endunless

                    <form method="POST" action="{{ route('checkin.verify', $sessionRecord->qr_token) }}" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" value="{{ old('full_name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select gender</option>
                                @foreach (['Male', 'Female'] as $gender)
                                    <option value="{{ $gender }}" @selected(old('gender') === $gender)>{{ $gender }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Application Number</label>
                            <input type="text" class="form-control" name="application_number" value="{{ old('application_number') }}" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100" @disabled(! $isCheckinOpen)>Verify and Check In</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
