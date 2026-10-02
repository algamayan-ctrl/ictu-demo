<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Completes mailbox ownership verification from a temporary signed URL.
 *
 * This endpoint intentionally does not require an authenticated session: a
 * newly registered user cannot sign in until the email is verified.
 */
class EmailVerificationController extends Controller
{
    public function verify(
        Request $request,
        User $user,
        string $hash,
        EmailVerificationService $verification,
        AuditService $audit,
    ): RedirectResponse {
        // New links use a host-independent signature so a verification
        // email may be opened from any device through the shared application
        // address. Keep the old absolute-signature check for links sent before
        // this update.
        if (! $request->hasValidRelativeSignature() && ! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors([
                'email' => 'This email verification link is invalid or has expired. Contact ICTU to resend a new verification email.',
            ]);
        }

        if (! hash_equals($verification->emailHash((string) $user->email), $hash)) {
            return redirect()->route('login')->withErrors([
                'email' => 'This verification link no longer matches the email currently registered for the account. Contact ICTU to resend verification.',
            ]);
        }

        if ($user->hasVerifiedInstitutionalEmail()) {
            return redirect()
                ->route('login')
                ->with('status', 'Your CSPC email address is already verified. You may sign in.');
        }

        $user->markInstitutionalEmailAsVerified();

        // Audit only the successful proof-of-mailbox event. A generated or
        // delivered email alone never marks an account as verified.
        $audit->record('USER_EMAIL_VERIFIED', $user, after: [
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Your CSPC email address has been verified. You may now sign in to SPMU-ACPMP.');
    }
}
