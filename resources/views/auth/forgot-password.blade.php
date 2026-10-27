@extends('layouts.app')

@section('body_class', 'login-body')

@section('content')
<div class="d-flex align-items-center justify-content-center py-5" style="min-height: calc(100vh - 120px);">
    <div class="card border-0 shadow-lg" style="width: 100%; max-width: 470px; border-radius: 1.5rem;">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" width="58" height="58" style="object-fit: contain;">
                <div>
                    <p class="text-uppercase text-primary fw-bold mb-0" style="font-size: .75rem; letter-spacing: .08em;">Account recovery</p>
                    <h1 class="h4 fw-bold mb-0" style="color: #0f172a;">Forgot your password?</h1>
                </div>
            </div>

            <p class="text-secondary mb-4">Enter your staff email address and we will send you a secure password reset link.</p>

            @if (session('status'))
                <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <label for="email" class="form-label fw-semibold">Email address</label>
                <div class="input-group mb-4">
                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control form-control-lg @error('email') is-invalid @enderror" autocomplete="email" autofocus required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">Email password reset link</button>
            </form>

            <div class="text-center mt-4">
                <a href="{{ route('login') }}" class="text-decoration-none fw-semibold"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a>
            </div>
        </div>
    </div>
</div>
@endsection
