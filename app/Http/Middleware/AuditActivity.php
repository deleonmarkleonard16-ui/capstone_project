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
    private const IGNORED_ROUTES = [
        'answers.save-progress',
        'guidance.progress',
        'api.notifications.clear-module',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Global coverage also captures failures before route middleware runs.
        // Avoid duplicate entries if a route explicitly includes this middleware.
        if ($request->attributes->get('audit_activity_running')) {
            return $next($request);
        }
        $request->attributes->set('audit_activity_running', true);
        $response = $next($request);
        $user = $request->user();
        $status = $this->outcomeStatus($request, $response);
        $failed = $status >= 400;

        if (! $failed && (! $user || ! in_array($user->role, ['admin', 'staff'], true)
            || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true))) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName();

        if (! $failed && in_array($routeName, self::IGNORED_ROUTES, true)) {
            return $response;
        }

        // Route templates avoid recording bearer tokens or personal data in URLs.
        $action = $routeName ? str_replace(['.', '-', '_'], ' ', $routeName) : $request->method().' '.($route?->uri() ?? 'unmatched route');
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
                'user_id' => $user?->getKey(),
                'user_name' => $user?->name ?? 'Guest (unauthenticated)',
                'user_role' => $user?->role ?? 'guest',
                'action' => ucfirst(substr($action, 0, 160)),
                'method' => $request->method(),
                'route' => $routeName,
                'subject' => $subject ?: null,
                'response_status' => $status,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to write audit log', ['exception' => $exception]);
        }

        return $response;
    }

    private function outcomeStatus(Request $request, Response $response): int
    {
        $status = (int) $request->attributes->get('audit_response_status', $response->getStatusCode());
        if ($status >= 400) {
            return $status;
        }

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data) && (($data['success'] ?? null) === false || ($data['ok'] ?? null) === false)) {
                return 422;
            }
        }

        if ($response->isRedirection() && $request->hasSession()) {
            // StartSession has already aged flash data: only this response's
            // flashed keys remain. Old errors must not taint the next activity.
            $session = $request->session();
            $flashed = array_merge($session->get('_flash.new', []), $session->get('_flash.old', []));
            if (in_array('errors', $flashed, true) && $session->get('errors')?->any()) {
                return $request->routeIs('login.store') ? 401 : 422;
            }
            if (in_array('error', $flashed, true) && $session->has('error')) {
                return 422;
            }
            if (in_array('warning', $flashed, true) && $session->has('warning')) {
                return 409;
            }
        }

        return $status;
    }
}
