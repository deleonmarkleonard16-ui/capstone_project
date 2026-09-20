<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('guidance.batch.{batchId}', function ($user, $batchId) {
    return in_array($user->role, ['admin', 'staff'], true)
        && \App\Models\GuidanceTestBatch::whereKey($batchId)->exists();
});

Broadcast::channel('guidance.proctoring', fn ($user) => in_array($user->role, ['admin', 'staff'], true));
