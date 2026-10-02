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
        string $user,
        string $hash,
        EmailVerificationService $verification,
        AuditService $audit,
    ): RedirectResponse {
        // New links use host-independent signatures so a recipient may open the
        // email on another device. Keep accepting legacy absolute signatures.
        if (! $request->hasValidRelativeSignature() && ! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors([
                'email' => 'This email verification link is invalid or has expired. Contact ICTU to resend a new verification email.',
            ]);
        }

        $account = User::query()->find($user);

        if (! $account) {
            return redirect()->route('login')->withErrors([
                'email' => 'This verification link points to an account that is not available on this SPMU-ACPMP server. Use the latest verification email or contact ICTU.',
            ]);
        }

        if (! hash_equals($verification->emailHash((string) $account->email), $hash)) {
            return redirect()->route('login')->withErrors([
                'email' => 'This verification link no longer matches the email currently registered for the account. Contact ICTU to resend verification.',
            ]);
        }

        if ($account->hasVerifiedInstitutionalEmail()) {
            return redirect()
                ->route('login')
                ->with('status', 'Your CSPC email address is already verified. You may sign in.');
        }

        $account->markInstitutionalEmailAsVerified();

        // Audit only the successful proof-of-mailbox event. A generated or
        // delivered email alone never marks an account as verified.
        $audit->record('USER_EMAIL_VERIFIED', $account, after: [
            'email' => $account->email,
            'email_verified_at' => $account->email_verified_at?->toIso8601String(),
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Your CSPC email address has been verified. You may now sign in to SPMU-ACPMP.');
    }
}
