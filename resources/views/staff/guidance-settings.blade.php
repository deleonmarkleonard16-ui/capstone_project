@extends('layouts.app')
@section('content')
<h1 class="h3 mb-3">System Settings</h1>
<ul class="nav nav-tabs mb-4" role="tablist" aria-label="System settings">
    <li class="nav-item"><a class="nav-link {{ request('tab', 'courses') === 'courses' ? 'active' : '' }}" href="{{ route(auth()->user()->role.'.settings.index', ['tab' => 'courses']) }}">Course / Program Management</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'staff' ? 'active' : '' }}" href="{{ route(auth()->user()->role.'.settings.index', ['tab' => 'staff']) }}">Guidance Staff Account Management</a></li>
</ul>

@if(request('tab', 'courses') === 'courses')
<div class="card p-4"><h2 class="h5">Official programs</h2><p>The ten official program options are standardized for all modules.</p><table class="table"><thead><tr><th>Code</th><th>Program Title</th></tr></thead><tbody>@foreach(\App\Support\CourseCatalog::OPTIONS as $code => $title)<tr><td>{{ $code }}</td><td>{{ $title }}</td></tr>@endforeach</tbody></table></div>
@elseif(request('tab') === 'staff')
<div class="card p-4">
    <h2 class="h5">Create guidance staff account</h2>
    <form method="post" action="{{ route(auth()->user()->role.'.settings.user') }}" class="row g-2 align-items-end mb-4">@csrf
        <input type="hidden" name="is_active" value="1">
        <div class="col-lg-3"><label class="form-label" for="new-staff-name">Name</label><input id="new-staff-name" name="name" class="form-control" required maxlength="255"></div>
        <div class="col-lg-3"><label class="form-label" for="new-staff-email">Email</label><input id="new-staff-email" type="email" name="email" class="form-control" required></div>
        <div class="col-lg-3"><label class="form-label" for="new-staff-password">Temporary password</label><input id="new-staff-password" type="password" name="password" minlength="12" class="form-control" required autocomplete="new-password"></div>
        <div class="col-lg-3"><button class="btn btn-primary w-100">Create staff account</button></div>
    </form>
    <h2 class="h5">Staff accounts</h2>
    <p class="text-muted">Leave the password blank to keep it. Enter a new password to reset credentials. Disabled accounts remain available in historic records.</p>
    @foreach($users as $staff)
        <form method="post" action="{{ route(auth()->user()->role.'.settings.user', $staff) }}" class="row g-2 align-items-end border-top py-3">@csrf
            <input type="hidden" name="is_active" value="0">
            <div class="col-lg-3"><label class="form-label" for="staff-name-{{ $staff->id }}">Name</label><input id="staff-name-{{ $staff->id }}" name="name" value="{{ $staff->name }}" class="form-control" required></div>
            <div class="col-lg-3"><label class="form-label" for="staff-email-{{ $staff->id }}">Email</label><input id="staff-email-{{ $staff->id }}" type="email" name="email" value="{{ $staff->email }}" class="form-control" required></div>
            <div class="col-lg-3"><label class="form-label" for="staff-password-{{ $staff->id }}">Reset password</label><input id="staff-password-{{ $staff->id }}" type="password" name="password" minlength="12" class="form-control" autocomplete="new-password" placeholder="Keep current password"></div>
            <div class="col-lg-2"><div class="form-check form-switch"><input id="staff-active-{{ $staff->id }}" class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" @checked($staff->is_active)><label for="staff-active-{{ $staff->id }}" class="form-check-label">Access enabled</label></div></div>
            <div class="col-lg-1"><button class="btn btn-outline-primary">Save</button></div>
        </form>
    @endforeach
</div>
@endif
@endsection
