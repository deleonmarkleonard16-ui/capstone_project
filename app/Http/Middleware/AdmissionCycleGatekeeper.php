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
     * Browser requests → redirect to Admission Cycle setup page with error banner.
     * AJAX / JSON requests → return 409 JSON so tests and JS callers can detect it.
     *
     * Maintenance Mode: if the active cycle has status='Maintenance', encoding
     * operations are blocked (same as no cycle) — only Setup/Cycle management,
     * Archive, and Analytics overview routes remain open.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $activeCycle = AdmissionCycle::active();

        // 1. Active cycle that is NOT in maintenance → allow all routes
        if ($activeCycle?->isActive()) {
            return $next($request);
        }

        // 2. These routes are always accessible (cycle management + overview)
        if ($request->routeIs(
            'admin.admission.index',
            'admin.admission.cycles.*'
        )) {
            return $next($request);
        }

        // All remaining Admission routes require an active cycle. This prevents
        // manually entered URLs from bypassing the disabled sidebar links.
        $message = $activeCycle
            ? 'The Admission Cycle is currently in Maintenance Mode. Access is suspended until the Guidance Admin exits maintenance.'
            : 'Active Admission Cycle Required: Please initialize or activate an Admission Cycle before accessing Masterlist, Encoding Sheet, or Test Sessions.';

        // 5a. AJAX / JSON / API requests → 409 JSON so tests & JS callers can detect it
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 409);
        }

        // 5b. Browser requests → redirect to Admission Cycle setup with a clear error banner
        return redirect()
            ->route('admin.admission.index')
            ->with('error', $message);
    }
}
