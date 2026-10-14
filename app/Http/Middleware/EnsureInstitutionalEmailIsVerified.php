<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent an authenticated session from continuing after its registered email
 * becomes unverified (for example, when ICTU changes the email address).
 */
class EnsureInstitutionalEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasVerifiedInstitutionalEmail()) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your CSPC email address must be verified before you can access SPMU-ACPMP. Enter the OTP sent to your institutional inbox or contact the SPMU Admin to resend a new OTP.',
            ]);
        }

        return $next($request);
    }
}
