<?php

namespace App\Http\Middleware;

use App\Models\AdmissionCycle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdmissionCycleGatekeeper
{
    /**
     * Enforces that an active (non-maintenance) Admission Cycle exists before
     * granting access to Masterlist, Encoding Sheet, Test Sessions, Scanners.
     *
     * HTTP 409 is returned for all blocked requests so feature tests can
     * assertStatus(409) on both browser and API requests.
     *
     * Maintenance Mode: if the active cycle has status='Maintenance', encoding
     * operations are blocked (same as no cycle) — only Setup/Cycle management
     * routes remain open.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $activeCycle = AdmissionCycle::active();

        // 1. Active cycle that is NOT in maintenance → allow
        if ($activeCycle && $activeCycle->status !== AdmissionCycle::STATUS_MAINTENANCE) {
            return $next($request);
        }

        // 2. Setup & cycle lifecycle routes are always open
        if ($request->routeIs('admin.admission.index', 'admin.admission.cycles.*')) {
            return $next($request);
        }

        // 3. Allow read-only access to archived records when cycle_id is provided
        if (
            $request->routeIs(
                'admin.admission.masterlist',
                'admin.admission.report',
                'admin.admission.analytics',
                'admin.admission.archive'
            )
            && $request->filled('cycle_id')
            && AdmissionCycle::whereKey($request->integer('cycle_id'))->exists()
        ) {
            return $next($request);
        }

        // 4. Maintenance mode message
        $message = $activeCycle
            ? 'The Admission Cycle is currently in Maintenance Mode. Access is suspended until the Guidance Admin exits maintenance.'
            : 'Active Admission Cycle Required: Please initialize or activate an Admission Cycle before accessing Masterlist, Encoding Sheet, or Test Sessions.';

        abort(409, $message);
    }
}
