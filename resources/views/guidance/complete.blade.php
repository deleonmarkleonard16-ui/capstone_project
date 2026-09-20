@extends('guidance.layout')
@section('content')
<div class="card p-4 text-center">
    <h1 class="h3">{{ $terminated ? 'Assessment ended by proctor' : 'Assessment completed' }}</h1>
    <p>Your saved responses have been recorded for Guidance Office review. This assessment pass is inactive.</p>
    <a class="btn btn-primary" href="{{ route('portal.index') }}?service=testing#track">Return to portal</a>
</div>
@endsection
