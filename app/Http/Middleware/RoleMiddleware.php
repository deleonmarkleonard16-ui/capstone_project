<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $role = $user?->role;

        // Keep the legacy role_id representation working while roles are being
        // normalized. Role ID 1 is the administrator role.
        if ($user && (int) $user->role_id === 1) {
            $role = 'admin';
        }

        abort_unless($user && $user->is_active !== false && in_array($role, $roles, true), 403);

        return $next($request);
    }
}
