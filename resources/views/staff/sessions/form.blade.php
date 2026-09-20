@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card page-card">
                <div class="card-body p-4">
                    <h1 class="h3 mb-4">{{ $session->exists ? 'Edit Test Session' : 'Create Test Session' }}</h1>
                    <form method="POST" action="{{ $formAction }}" class="row g-3">
                        @csrf
                        @if ($method !== 'POST')
                            @method($method)
                        @endif
                        <div class="col-12">
                            <label class="form-label">Session Title</label>
                            <input type="text" class="form-control" name="title" value="{{ old('title', $session->title) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Exam Date</label>
                            <input type="date" class="form-control" name="exam_date" value="{{ old('exam_date', optional($session->exam_date)->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="start_time" value="{{ old('start_time', $session->start_time) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" value="{{ old('end_time', $session->end_time) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" name="duration_minutes" value="{{ old('duration_minutes', $session->duration_minutes ?: 40) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room</label>
                            <input type="text" class="form-control" name="room" value="{{ old('room', $session->room) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                @foreach (['draft', 'scheduled', 'in_progress', 'completed'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $session->status ?: 'scheduled') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route(auth()->user()->role.'.sessions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button class="btn btn-primary" type="submit">Save Session</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
