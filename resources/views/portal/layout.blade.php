<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student & Alumni Services | PSU SCC</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f0f4fc;color:#19345e;font:16px/1.5 system-ui,sans-serif}header{background:white;border-bottom:4px solid #194896;padding:18px max(20px,calc((100% - 1060px)/2));display:flex;gap:14px;align-items:center}header img{width:50px;height:50px}header small{display:block;color:#65748a}main{max-width:1100px;margin:30px auto;padding:0 20px}h1{font-size:28px}h2{font-size:21px;margin-top:0}a{color:#194896}.card{background:white;padding:26px;border-radius:18px;margin:22px 0;box-shadow:0 6px 24px #17305f0b}.services{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.service{padding:22px;border:1px solid #d7dfec;border-radius:12px;text-decoration:none;color:inherit}.service strong{display:block;font-size:18px}.service:hover{border-color:#194896;background:#f5f8ff}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.full{grid-column:1/-1}label{display:block;font-size:14px;font-weight:600;margin-bottom:6px}input:not([type=checkbox]),select,textarea{width:100%;padding:11px;border:1px solid #b7c5d8;border-radius:7px;font:inherit}textarea{min-height:100px}button,.button{display:inline-block;border:0;border-radius:7px;padding:12px 20px;background:#194896;color:white;font:inherit;text-decoration:none;cursor:pointer}fieldset{border:1px solid #d7dfec;border-radius:8px;padding:16px}legend{font-weight:600}.muted{color:#65748a}.notice{padding:20px;background:#e7f5eb;border-radius:12px;overflow-wrap:anywhere}.error{background:#fff0ee;padding:18px;border-radius:12px}input:focus,select:focus,textarea:focus,a:focus-visible,button:focus-visible{outline:3px solid #e9bd37;outline-offset:2px}[hidden]{display:none!important}@media(max-width:650px){.services,.grid{grid-template-columns:1fr}.card{padding:18px}header{padding:15px}h1{font-size:24px}}
.request-banner{background:linear-gradient(110deg,#194896,#102f6d);color:white;padding:20px;border-radius:12px;font-weight:700;font-size:20px;margin-bottom:20px}.request-tabs{display:flex;gap:14px;margin-bottom:24px}.request-tabs a{flex:1;text-align:center}.secondary{background:white;color:#194896;border:1px solid #aabfe6}#test-fields{background:linear-gradient(110deg,#fff8e6,#f0f3f9)}#request .grid{grid-template-columns:repeat(3,minmax(0,1fr))}@media(max-width:650px){#request .grid{grid-template-columns:1fr}.request-tabs{flex-direction:column}}.service.selected{border-color:#194896;background:#f8faff}.service-icon{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:#3278df;color:white;font-weight:700;margin-bottom:12px}.service-icon.good-moral{background:#d7a000}.service-icon.exit-form{background:#dd3e48}        body{--accent:#194896;--accent-deep:#102f6d;--tint:#eef4ff;--accent-border:#aabfe6;--focus:#3278df;background:var(--tint)}
        body[data-service="good-moral"]{--accent:#856000;--accent-deep:#624700;--tint:#fff9e9;--accent-border:#d7bd71;--focus:#a87a00}
        body[data-service="exit-form"]{--accent:#b52429;--accent-deep:#801f23;--tint:#fff1f1;--accent-border:#dfa2a5;--focus:#cb353d}
        header{border-bottom-color:var(--accent)}
        a{color:var(--accent)}
        button,.button{background:var(--accent);color:white}
        button:hover,.button:hover{background:var(--accent-deep);color:white}
        .request-banner{background:linear-gradient(110deg,var(--accent),var(--accent-deep))}
        .secondary{background:white;color:var(--accent);border-color:var(--accent-border)}
        .secondary:hover{background:var(--tint);color:var(--accent-deep)}
        .service.selected,.service:hover{border-color:var(--accent);background:var(--tint)}
        input[type=checkbox]{accent-color:var(--accent)}
        input:focus,select:focus,textarea:focus,a:focus-visible,button:focus-visible{outline-color:var(--focus)}
        #test-fields{background:linear-gradient(110deg,var(--tint),#f8fafc)}
        body.review-open{overflow:hidden}
        .review-dialog{border:0;border-radius:20px;padding:24px;width:min(1000px,calc(100% - 32px));max-height:calc(100dvh - 48px);color:#19345e;background:white;box-shadow:0 24px 80px #0003;overflow-y:auto}
        .review-dialog::backdrop{background:rgba(0,0,0,.5)}
        .review-heading{display:flex;align-items:center;justify-content:space-between;gap:16px}
        .review-heading h2{font-size:28px;margin:0;font-weight:600}
        .review-close{background:transparent;color:#777;font-size:34px;line-height:1;padding:0 4px}
        .review-close:hover{background:transparent;color:var(--accent)}
        #review-description{margin:6px 0 22px;font-size:18px}
        .review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:0 0 24px}
        .review-summary{padding:20px;border:1px solid #dfe4ed;border-radius:20px;background:linear-gradient(120deg,var(--tint),#f2f4f8);overflow-wrap:anywhere}
        .review-summary dt{font-size:15px;font-weight:400;color:#62666d;margin-bottom:4px}
        .review-summary dd{margin:0;font-size:18px;font-weight:600}
        .review-actions{display:flex;justify-content:flex-end;gap:10px}
        @media(max-width:650px){.review-dialog{padding:20px}.review-heading h2{font-size:22px}.review-grid{grid-template-columns:1fr;gap:12px}.review-summary{padding:16px}.review-actions{flex-wrap:wrap}}
        .review-dialog{text-transform:uppercase}
        #first_name,#middle_name,#last_name,#student_number{text-transform:uppercase}
        @media print {
            body.printing-guidance-stub .payment-stub:not(.selected-stub){display:none!important}
            body{background:white!important}body *{visibility:hidden}
            .payment-stub,.payment-stub *{visibility:visible}
            .payment-stub{position:absolute;left:0;top:0;width:100%;margin:0;padding:24px;box-shadow:none;border:1px solid #aaa}
            .no-print,.no-print *{display:none!important}
            .payment-stub .request-banner{background:white;color:black;border-bottom:2px solid black}
        }
    </style>
</head>
<body data-service="{{ $entry->service ?? $selectedService ?? 'testing' }}">
<header><img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo"><div><strong>Pangasinan State University – San Carlos Campus</strong><small>Student & Alumni Services</small></div></header>
<main>
    @if($errors->any())<div class="error" role="alert"><strong>Please check your details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
