@extends('layouts.app')
@section('content')
<h1 class="h3 mb-2">Guidance Staff Settings</h1>
<p class="text-muted">Update your profile and password.</p>
<form method="post" action="{{ route('staff.settings.update') }}" class="card page-card p-4" style="max-width:640px">
    @csrf
    <label for="settings-name" class="form-label">Name</label>
    <input id="settings-name" name="name" class="form-control mb-3" value="{{ old('name', $staff->name) }}" required maxlength="255" autocomplete="name">
    <label for="settings-email" class="form-label">Email</label>
    <input id="settings-email" type="email" name="email" class="form-control mb-3" value="{{ old('email', $staff->email) }}" required autocomplete="email">
    <label for="settings-current-password" class="form-label">Current password</label>
    <input id="settings-current-password" type="password" name="current_password" class="form-control mb-3" required autocomplete="current-password">
    <label for="settings-password" class="form-label">New password (optional, at least 8 characters)</label>
    <input id="settings-password" type="password" name="password" class="form-control mb-3" minlength="8" autocomplete="new-password">
    <label for="settings-password-confirmation" class="form-label">Confirm new password</label>
    <input id="settings-password-confirmation" type="password" name="password_confirmation" class="form-control mb-3" autocomplete="new-password">
    <button class="btn btn-primary align-self-start">Save Settings</button>
</form>
@endsection
