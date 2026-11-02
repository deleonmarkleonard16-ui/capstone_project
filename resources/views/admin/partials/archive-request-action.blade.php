@php
    $requestModules = $req->service === 'testing'
        ? array_values(array_intersect($req->tests ?: [], array_keys(\App\Services\GuidanceCategories::LABELS)))
        : [$req->service];
    $detailModule = in_array(request('test_type'), $requestModules, true) ? request('test_type') : ($requestModules[0] ?? null);
    if ($req->batch_id) {
        $batchParameters = ['archived' => 1, 'q' => $req->batch?->batch_name ?: $req->reference];
        if ($req->service === 'testing') $batchParameters['category'] = $detailModule;
        $actionUrl = route($archiveRole.($req->service === 'testing' ? '.guidance-batches.index' : '.'.$req->service.'.batches'), $batchParameters);
    } else {
        $actionUrl = route($archiveRole.'.requests.details', ['serviceRequest' => $req, 'module_type' => $detailModule]);
    }
@endphp
<a class="btn btn-sm btn-outline-secondary" href="{{ $actionUrl }}">{{ $req->batch_id ? 'Open' : 'View Details' }}</a>
