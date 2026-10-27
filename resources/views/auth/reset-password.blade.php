@extends('layouts.app')

@section('body_class', 'login-body')

@section('content')
<div class="d-flex align-items-center justify-content-center py-5" style="min-height: calc(100vh - 120px);">
    <div class="card border-0 shadow-lg" style="width: 100%; max-width: 500px; border-radius: 1.5rem;">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" width="58" height="58" style="object-fit: contain;">
                <div>
                    <p class="text-uppercase text-primary fw-bold mb-0" style="font-size: .75rem; letter-spacing: .08em;">Account recovery</p>
                    <h1 class="h4 fw-bold mb-0" style="color: #0f172a;">Choose a new password</h1>
                </div>
            </div>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="form-control form-control-lg" autocomplete="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">New password</label>
                    <input id="password" name="password" type="password" class="form-control form-control-lg" autocomplete="new-password" required>
                </div>
                <div class="mb-4">
                    <label for="password_confirmation" class="form-label fw-semibold">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control form-control-lg" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">Reset password</button>
            </form>

            <div class="text-center mt-4"><a href="{{ route('login') }}" class="text-decoration-none fw-semibold">Back to sign in</a></div>
        </div>
    </div>
</div>
@endsection
