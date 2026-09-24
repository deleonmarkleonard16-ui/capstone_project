<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request): string {
            $role = $request->user()->role;
            abort_unless(in_array($role, ['admin', 'staff'], true), 403);

            return route($role.'.analytics');
        });

        // Trust Render's reverse proxy / load balancer so Laravel reads the correct
        // X-Forwarded-Proto header and generates HTTPS URLs behind SSL termination.
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'admission.cycle' => \App\Http\Middleware\AdmissionCycleGatekeeper::class,
        ]);

        // Student-facing portal & assessment routes are unauthenticated public endpoints.
        // Exempt them from CSRF so students never encounter 419 Page Expired errors.
        $middleware->validateCsrfTokens(except: [
            'portal/*',
            'api/*',
            'test/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle CSRF / session-expiry (419 Page Expired) gracefully.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            $status = $e->getStatusCode();

            if ($status === 419) {
                $previous = $request->headers->get('referer', '/portal');
                if (str_contains($previous, '/portal')) {
                    return redirect('/portal')
                        ->withInput($request->except(['_token', 'password', 'password_confirmation']))
                        ->with('portal_notice', 'Your session expired — your details have been restored. Please review and resubmit.');
                }
                return redirect($previous ?: '/portal')
                    ->with('portal_notice', 'Your session expired. Please try again.');
            }
        });
    })->create();
