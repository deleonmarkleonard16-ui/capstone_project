@extends('layouts.app')
@section('content')
<h1 class="h3 mb-3">System Settings</h1>
<ul class="nav nav-tabs mb-4" role="tablist" aria-label="System settings">
    <li class="nav-item"><a class="nav-link {{ request('tab', 'courses') === 'courses' ? 'active' : '' }}" href="{{ route(auth()->user()->role.'.settings.index', ['tab' => 'courses']) }}">Course / Program Management</a></li>
    <li class="nav-item"><a class="nav-link {{ request('tab') === 'staff' ? 'active' : '' }}" href="{{ route(auth()->user()->role.'.settings.index', ['tab' => 'staff']) }}">Guidance Staff Account Management</a></li>
</ul>

@if(request('tab', 'courses') === 'courses')
<div class="card p-4">
    <h2 class="h5">Add program</h2>
    <p class="text-muted">Disabling a program prevents new selections and keeps existing records.</p>
    <form method="post" action="{{ route(auth()->user()->role.'.settings.course') }}" class="row g-2 align-items-end mb-4">@csrf
        <div class="col-lg-3"><label for="new-course-code" class="form-label">Code</label><input id="new-course-code" name="code" class="form-control" required maxlength="30" pattern="[A-Za-z0-9-]+" value="{{ old('code') }}" placeholder="e.g. BSIT"></div>
        <div class="col-lg-6"><label for="new-course-name" class="form-label">Program name</label><input id="new-course-name" name="name" class="form-control" maxlength="255" required value="{{ old('name') }}"></div>
        <input type="hidden" name="is_active" value="1"><div class="col-lg-3"><button class="btn btn-primary w-100">Add program</button></div>
    </form>
    <h2 class="h5 mb-2">Existing programs</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" style="min-width:650px">
            <thead><tr><th scope="col" style="width:13%">Code</th><th scope="col">Program Title</th><th scope="col" style="width:16%">Status</th><th scope="col" style="width:10%">Actions</th></tr></thead>
            <tbody>
            @foreach($courses as $course)
                <tr>
                    <th scope="row" class="fw-semibold">{{ $course->code }}</th>
                    <td>
                        <form id="course-form-{{ $course->id }}" method="post" action="{{ route(auth()->user()->role.'.settings.course', $course) }}">@csrf
                            <input type="hidden" name="code" value="{{ $course->code }}"><input type="hidden" name="is_active" value="0">
                        </form>
                        <label class="visually-hidden" for="course-name-{{ $course->id }}">Program title for {{ $course->code }}</label>
                        <input id="course-name-{{ $course->id }}" form="course-form-{{ $course->id }}" name="name" value="{{ $course->name }}" class="form-control form-control-sm" required maxlength="255">
                    </td>
                    <td><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" form="course-form-{{ $course->id }}" name="is_active" value="1" id="course-active-{{ $course->id }}" @checked($course->is_active) onchange="this.nextElementSibling.textContent = this.checked ? 'Active' : 'Inactive'"><label class="form-check-label" for="course-active-{{ $course->id }}">{{ $course->is_active ? 'Active' : 'Inactive' }}</label></div></td>
                    <td><button type="submit" form="course-form-{{ $course->id }}" class="btn btn-outline-primary btn-sm">Save</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
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
