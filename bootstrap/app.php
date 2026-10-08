<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        
        $middleware->alias([
            'staff' => \App\Http\Middleware\StaffMiddleware::class,
            'clinic' => \App\Http\Middleware\EnsureClinicStaff::class,
            'no-store' => \App\Http\Middleware\SetNoStoreHeaders::class,
        ]);

        // Where the "guest" middleware sends already-authenticated users
        // (e.g. an authenticated user opening /login). Clinic staff keep the
        // existing dashboard destination; patient-role users are sent to the
        // neutral Patient Portal holding page instead of any clinic route.
        // Unknown roles fall through to /dashboard, which the 'clinic'
        // middleware rejects with 403.
        $middleware->redirectUsersTo(function ($request) {
            if (($request->user()?->role) === 'patient') {
                return route('patient.portal.coming-soon');
            }

            return route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expired-session logout recovery (narrowly scoped).
        //
        // VerifyCsrfToken throws TokenMismatchException, but Laravel's
        // exception Handler converts it to HttpException(419) in
        // prepareException() BEFORE render callbacks run
        // (Foundation\Exceptions\Handler::render()), so this callback
        // matches the transformed exception and verifies its previous
        // exception to remain limited to genuine CSRF token mismatches.
        //
        // It fires ONLY for POST /logout: the browser is sent to the login
        // route with a friendly status message instead of parking on
        // /logout with a 419 (whose subsequent refresh produced a GET
        // /logout -> 405). Every other 419, method, and path keeps the
        // default error response; the logout controller is never executed
        // for a mismatched token, and valid POST /logout behavior,
        // session invalidation, and CSRF enforcement are untouched.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419
                || ! $e->getPrevious() instanceof TokenMismatchException
                || ! $request->isMethod('POST')
                || ! $request->is('logout')) {
                return null;
            }

            return redirect()
                ->route('login')
                ->with('status', 'Your session has expired. Please log in again.');
        });
    })->create();
