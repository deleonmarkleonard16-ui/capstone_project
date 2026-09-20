@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card page-card">
                <div class="card-body p-4">
                    <h1 class="h3 mb-4">{{ $applicant->exists ? 'Edit Applicant' : 'Add Applicant' }}</h1>
                    <form method="POST" action="{{ $formAction }}" class="row g-3">
                        @csrf
                        @if ($method !== 'POST')
                            @method($method)
                        @endif
                        <div class="col-md-6">
                            <label class="form-label">Application Number</label>
                            <input type="text" class="form-control" name="application_number" value="{{ old('application_number', $applicant->application_number) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-control" name="status" value="{{ old('status', $applicant->status ?: 'approved') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" value="{{ old('first_name', $applicant->first_name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" value="{{ old('middle_name', $applicant->middle_name) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" value="{{ old('last_name', $applicant->last_name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" required>
                                @foreach (['Male', 'Female'] as $gender)
                                    <option value="{{ $gender }}" @selected(old('gender', $applicant->gender) === $gender)>{{ $gender }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="{{ old('email', $applicant->email) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" value="{{ old('contact_number', $applicant->contact_number) }}">
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route(auth()->user()->role.'.applicants.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button class="btn btn-primary" type="submit">Save Applicant</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
