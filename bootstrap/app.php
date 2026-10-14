<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRegistrationIsApproved;
use App\Http\Middleware\EnsureActiveWorkspace;
use App\Http\Middleware\EnsureInstitutionalEmailIsVerified;
use App\Http\Middleware\EnforceIdleSession;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\PreventDuplicateSubmission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

