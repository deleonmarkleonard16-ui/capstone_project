@php
    $letterheadPath = public_path('images/admission/download-letterhead.png');
    $letterheadSrc = 'data:image/png;base64,'.base64_encode(file_get_contents($letterheadPath));
@endphp
<img class="assessment-letterhead" src="{{ $letterheadSrc }}" alt="Pangasinan State University — San Carlos City Campus letterhead">
