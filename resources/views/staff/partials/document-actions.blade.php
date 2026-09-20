@if($entry->status === 'proof_review')
    <button type="button" class="btn btn-sm btn-outline-primary" data-receipt="{{ route(auth()->user()->role.'.documents.proof', $entry) }}">Review receipt</button>
    <form method="post" action="{{ route(auth()->user()->role.'.'.$entry->service.'.update', $entry) }}" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="action" value="verify"><button class="btn btn-sm btn-primary">Approve / Ready for Pickup</button></form>
@elseif($entry->status === 'ready')
    <form method="post" action="{{ route(auth()->user()->role.'.'.$entry->service.'.update', $entry) }}" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="action" value="claim"><button class="btn btn-sm btn-primary">Mark as Claimed / Completed</button></form>
@endif
