<?php

namespace App\Http\Middleware;

use App\Models\AdmissionCycle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdmissionCycleGatekeeper
{
    /**
     * Handle an incoming request.
     * Enforces that an active Admission Cycle exists before granting access
     * to Masterlist, Encoding Sheet, Test Sessions, and Examination Scanners.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $activeCycle = AdmissionCycle::active();

        // 1. If an active cycle exists, allow request
        if ($activeCycle) {
            return $next($request);
        }

        // 2. Allow cycle configuration hub and cycle lifecycle actions
        if ($request->routeIs('admin.admission.index', 'admin.admission.cycles.*')) {
            return $next($request);
        }

        // 3. Allow inspecting historic archived records if an explicit cycle_id is specified
        if ($request->routeIs('admin.admission.masterlist', 'admin.admission.report', 'admin.admission.analytics') && $request->filled('cycle_id')) {
            $cycleId = $request->integer('cycle_id');
            if (AdmissionCycle::whereKey($cycleId)->exists()) {
                return $next($request);
            }
        }

        // 4. Block access to Masterlist, Encoding Sheet, Test Sessions, etc.
        return redirect()->route('admin.admission.index', ['require_cycle' => 1])
            ->with('warning', 'Active Admission Cycle Required: Please select an existing cycle or initialize a new cycle (e.g., S.Y. 2026 – 2027) before accessing Masterlist, Encoding Sheet, or Test Sessions.');
    }
}
