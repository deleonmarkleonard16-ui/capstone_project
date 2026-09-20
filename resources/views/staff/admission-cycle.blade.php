@extends('layouts.app')

@section('content')
    <h1 class="h3 mb-2">Admission Cycle</h1>
    <p class="text-muted mb-4">Manage applicants and admission test sessions.</p>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card page-card h-100"><div class="card-body p-4">
                <h2 class="h5">Applicants</h2>
                <p>Register, import, and update admission applicants.</p>
                <a class="btn btn-primary" href="{{ route(auth()->user()->role.'.applicants.index') }}">Manage Applicants</a>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card page-card h-100"><div class="card-body p-4">
                <h2 class="h5">Admission Test Sessions</h2>
                <p>Schedule tests, assign applicants, monitor attendance, and review results.</p>
                <a class="btn btn-primary" href="{{ route(auth()->user()->role.'.sessions.index') }}">Manage Test Sessions</a>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card page-card h-100"><div class="card-body p-4">
                <h2 class="h5">Admission Evaluation & Ranking</h2>
                <p>Encode High School GWA and Interview scores, compute institutional total marks (60% Exam + 20% GWA + 20% Interview), and rank examinees.</p>
                <a class="btn btn-primary" href="{{ route(auth()->user()->role.'.admission-evaluation.index') }}">Compute Total Marks & Rank</a>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card page-card h-100"><div class="card-body p-4">
                <h2 class="h5">Official List of Passers</h2>
                <p>Filter by rank cutoff and generate printable official PSU-CAT admission passer lists.</p>
                <a class="btn btn-outline-primary" href="{{ route(auth()->user()->role.'.admission-evaluation.index') }}">View Passers Ranking</a>
            </div></div>
        </div>
    </div>
@endsection