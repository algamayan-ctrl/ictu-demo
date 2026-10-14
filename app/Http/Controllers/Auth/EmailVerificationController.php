<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EmailVerificationService;
use App\Services\BorrowerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function create(Request $request): View
    {
        $email = Str::lower(
            trim((string) $request->query('email', ''))
        );

        return view('auth.verify-account', [
            'email' => $email,
        ]);
    }

    public function verify(
        Request $request,
        EmailVerificationService $verification,
        AuditService $audit,
    ): RedirectResponse {
        $request->merge([
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
            'otp' => preg_replace(
                '/\D+/',
                '',
                (string) $request->input('otp')
            ),
        ]);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $account = User::query()
            ->where('email', $data['email'])
            ->first();

        /*
         * Keep the response intentionally generic when the email does not exist
         * so this public page is not an institutional-account directory.
         */
        if (! $account) {
            return back()
                ->withErrors([
                    'otp' => 'The verification code is invalid, expired, or does not match a pending account.',
                ])
                ->withInput(['email' => $data['email']]);
        }

        $result = $verification->verify(
            $account,
            $data['otp']
        );

        if ($result === EmailVerificationService::RESULT_VERIFIED) {
            $account->refresh();
            if ($registration = $account->borrowerRegistration) {
                app(BorrowerRegistrationService::class)->notifySubmitted($registration);
            }

            $audit->record(
                'USER_EMAIL_VERIFIED',
                $account,
                after: [
                    'email' => $account->email,
                    'verification_method' => 'OTP',
                    'email_verified_at' => $account->email_verified_at?->toIso8601String(),
                ]
            );

            return redirect()
                ->route('login')
                ->with(
                    'status',
                    $account->hasApprovedRegistration()
                        ? 'Your email address has been verified. You may now sign in to SPMU-ACPMP.'
                        : 'Your email address has been verified. '.$account->registrationAccessMessage()
                );
        }

        if ($result === EmailVerificationService::RESULT_ALREADY_VERIFIED) {
            if ($registration = $account->borrowerRegistration) {
                app(BorrowerRegistrationService::class)->notifySubmitted($registration);
            }
            return redirect()
                ->route('login')
                ->with(
                    'status',
                    $account->hasApprovedRegistration()
                        ? 'Your email address is already verified. You may sign in.'
                        : 'Your email address is already verified. '.$account->registrationAccessMessage()
                );
        }

        $message = match ($result) {
            EmailVerificationService::RESULT_EXPIRED =>
                'This OTP has expired. Request a new verification code.',
            EmailVerificationService::RESULT_LOCKED =>
                'Too many incorrect OTP attempts. Request a new verification code.',
            EmailVerificationService::RESULT_MISSING =>
                'No active OTP was found for this account. Request a new verification code.',
            default =>
                'The verification code is incorrect. Check the latest OTP sent to your registered email.',
        };

        return back()
            ->withErrors(['otp' => $message])
            ->withInput(['email' => $data['email']]);
    }

    public function resend(
        Request $request,
        EmailVerificationService $verification,
    ): RedirectResponse {
        $request->merge([
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
        ]);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $data['email'];

        $account = User::query()
            ->where('email', $email)
            ->first();

        if (! $account) {
            return redirect()
                ->route('account.verification.show', ['email' => $email])
                ->with(
                    'status',
                    'If this email belongs to a pending SPMU-ACPMP account, a verification request has been processed.'
                );
        }

        if ($account->hasVerifiedInstitutionalEmail()) {
            return redirect()
                ->route('login')
                ->with(
                    'status',
                    $account->hasApprovedRegistration()
                        ? 'Your email address is already verified. You may sign in.'
                        : 'Your email address is already verified. '.$account->registrationAccessMessage()
                );
        }

        $wait = $verification->secondsUntilResendAllowed($account);

        if ($wait > 0) {
            return redirect()
                ->route('account.verification.show', ['email' => $email])
                ->withErrors([
                    'otp' => "Please wait {$wait} second(s) before requesting another OTP.",
                ]);
        }

        if (! $verification->send($account)) {
            return redirect()
                ->route('account.verification.show', ['email' => $email])
                ->withErrors([
                    'delivery' => 'The verification OTP could not be sent. Resend OTP or contact ICTU for assistance.',
                ]);
        }

        return redirect()
            ->route('account.verification.show', ['email' => $email])
            ->with(
                'status',
                'A new 6-digit OTP was sent to your registered email address.'
            );
    }
}
