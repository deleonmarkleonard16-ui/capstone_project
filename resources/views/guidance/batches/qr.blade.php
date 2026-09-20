@extends('guidance.layout')
@section('content')
<div class="text-center"><h1>{{ $batch->batch_name }}</h1><p>{{ $batch->courseLabel() }} · {{ $batch->test_type }}</p><img src="{{ $image }}" width="350" height="350" class="img-fluid" alt="Batch registration QR"><p><a href="{{ $url }}">{{ $url }}</a></p><p>Scan this code, verify your full name and student ID, then submit your receipt.</p><button class="btn btn-primary d-print-none" onclick="window.print()">Print Batch QR</button></div>
@endsection
