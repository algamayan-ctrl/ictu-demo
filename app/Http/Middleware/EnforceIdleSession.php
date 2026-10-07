<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Ends an authenticated SPMU-ACPMP session after a period with no
 * recorded user interaction.
 *
 * Important: normal requests do not refresh the idle clock. Only the
 * dedicated activity heartbeat, triggered by real browser interaction,
 * refreshes last_user_activity_at. This prevents background polling from
 * keeping an unattended session alive.
 */
class EnforceIdleSession
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $timeoutMinutes = max(
            1,
            (int) config('session.idle_timeout', 15)
        );

        $timeoutSeconds = $timeoutMinutes * 60;

        $lastActivity = (int) $request->session()->get(
            'last_user_activity_at',
            0
        );

        /*
         * First authenticated request after login initializes the idle clock.
         * Subsequent ordinary requests do NOT extend it.
         */
        if ($lastActivity <= 0) {
            $request->session()->put(
                'last_user_activity_at',
                now()->timestamp
            );

            return $next($request);
        }

        $inactiveFor = now()->timestamp - $lastActivity;

        if ($inactiveFor < $timeoutSeconds) {
            return $next($request);
        }

        /*
         * Preserve a technical audit record before destroying the session.
         */
        try {
            app(AuditService::class)->record(
                'SESSION_IDLE_TIMEOUT',
                $user,
                reason: "Authenticated session ended after {$timeoutMinutes} minutes of inactivity.",
                after: [
                    'idle_timeout_minutes' => $timeoutMinutes,
                    'last_user_activity_at' => date(
                        DATE_ATOM,
                        $lastActivity
                    ),
                ],
            );
        } catch (Throwable $exception) {
            /*
             * Audit failure must never prevent the security logout.
             */
            report($exception);
        }

        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with(
                'status',
                "Your session ended after {$timeoutMinutes} minutes of inactivity. Please sign in again."
            );
    }
}