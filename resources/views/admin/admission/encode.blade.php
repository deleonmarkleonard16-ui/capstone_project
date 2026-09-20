@extends('layouts.app')
@section('content')
<h1>Paper answer encoding</h1><p>{{ $applicant->full_name }} · {{ $applicant->application_number }}</p><form method="post" action="{{ route('admin.admission.encode.submit',$applicant) }}">@csrf<div class="row g-2">@for($i=1;$i<=80;$i++)<div class="col-6 col-sm-3 col-lg-2"><label class="form-label">Item {{ $i }}</label><select class="form-select" name="answers[{{ $i }}]"><option value="">Blank</option>@foreach(['A','B','C','D'] as $letter)<option value="{{ $letter }}">{{ $letter }}</option>@endforeach</select></div>@endfor</div><button class="btn btn-primary mt-3">Score answer sheet</button></form>
@endsection
