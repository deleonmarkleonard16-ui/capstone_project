<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class AuditActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'staff'], true)
            || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $response->getStatusCode() >= 400) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        $action = $routeName ? str_replace(['.', '-', '_'], ' ', $routeName) : $request->method().' '.$request->path();
        $subject = [];
        foreach (($route?->parameters() ?? []) as $key => $value) {
            if (is_object($value) && method_exists($value, 'getKey')) {
                $value = $value->getKey();
            }
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $subject[$key] = (int) $value;
            }
        }

        try {
            AuditLog::create([
                'user_id' => $user->getKey(),
                'user_name' => $user->name,
                'user_role' => $user->role,
                'action' => ucfirst($action),
                'method' => $request->method(),
                'route' => $routeName,
                'subject' => $subject ?: null,
                'response_status' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to write audit log', ['exception' => $exception]);
        }

        return $response;
    }
}
