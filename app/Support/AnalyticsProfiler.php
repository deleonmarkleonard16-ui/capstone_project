<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AnalyticsProfiler
{
    public function measure(string $stage, callable $operation): mixed
    {
        $context = ['stage' => $stage, 'request_id' => request()->header('X-Request-ID')];
        Log::info('Analytics stage started', $context);
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = clone $originalDispatcher;
        $count = 0;
        $queryMs = 0.0;
        $slowest = [];
        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $query) use (&$count, &$queryMs, &$slowest) {
            $count++;
            $queryMs += $query->time;
            // Never log SQL, bindings, student details, or assessment answers.
            $slowest[] = ['fingerprint' => hash('sha256', $query->sql), 'ms' => round($query->time, 2)];
            usort($slowest, fn ($a, $b) => $b['ms'] <=> $a['ms']);
            $slowest = array_slice($slowest, 0, 3);
        });
        $connection->setEventDispatcher($dispatcher);
        $start = hrtime(true);
        $completed = false;
        try {
            $result = $operation();
            $completed = true;
            return $result;
        } finally {
            $connection->setEventDispatcher($originalDispatcher);
            Log::info('Analytics stage finished', $context + [
                'completed' => $completed,
                'elapsed_ms' => round((hrtime(true) - $start) / 1e6, 2),
                'query_count' => $count, 'query_ms' => round($queryMs, 2),
                'slowest_queries' => $slowest,
            ]);
        }
    }
}
