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
    ->withMiddleware(function (Middleware $middleware) {
        // Trust Render's reverse proxy for correct SSL detection
        $middleware->trustProxies(at: '*');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // Student-facing portal & assessment routes are unauthenticated public endpoints
        // Exempt them from CSRF so students never encounter 419 Page Expired errors
        $middleware->validateCsrfTokens(except: [
            'portal/*',
            'api/*',
            'test/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle any remaining CSRF / session-expiry (419 Page Expired) gracefully.
        // In Laravel 11/12, TokenMismatchException is converted to HttpException(419)
        // prior to render callbacks, so we must intercept HttpException with status 419.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419) {
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
