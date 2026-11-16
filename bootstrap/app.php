<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRegistrationIsApproved;
use App\Http\Middleware\EnsureActiveWorkspace;
use App\Http\Middleware\EnsureInstitutionalEmailIsVerified;
use App\Http\Middleware\EnforceIdleSession;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\PreventDuplicateSubmission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\PostTooLargeException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configure the actual reverse-proxy addresses at deployment; requests
        // are not trusted merely for carrying forwarded headers.
        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }
        // Before the session starts, so no cookie is issued for a non-canonical host.
        $middleware->web(prepend: [
            RedirectToCanonicalHost::class,
        ]);
        $middleware->web(append: [
        SecurityHeaders::class,
        PreventDuplicateSubmission::class,
    ]);
        $middleware->alias([
            'approved-registration' => EnsureRegistrationIsApproved::class,
            'active' => EnsureAccountIsActive::class,
            'verified-email' => EnsureInstitutionalEmailIsVerified::class,
            'idle' => EnforceIdleSession::class,
            'role' => EnsureUserHasRole::class,
            'workspace' => EnsureActiveWorkspace::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is the upload-size response before PHP can pass an oversized form to validation.
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            $message = 'The combined upload exceeds the server limit. Please choose smaller files and try again. No submission was saved.';

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return response()->view('errors.413', ['message' => $message], 413);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

