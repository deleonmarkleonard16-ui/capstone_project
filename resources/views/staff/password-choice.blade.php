@extends('layouts.app')
@section('content')
<div class="card page-card shadow-sm p-4 mx-auto" style="max-width:640px">
    <span class="badge bg-primary align-self-start mb-3">First sign-in</span>
    <h1 class="h3 mb-2">Would you like to change your password?</h1>
    <p class="text-muted mb-4">You may replace the temporary password now or keep it. Password changes remain available from Staff Settings at any time.</p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route('staff.settings.index') }}">
            <i class="bi bi-key me-1"></i> Change Password
        </a>
        <form method="post" action="{{ route('staff.password-choice.keep') }}">
            @csrf
            <button class="btn btn-outline-secondary">Keep Current Password</button>
        </form>
    </div>
</div>
@endsection
