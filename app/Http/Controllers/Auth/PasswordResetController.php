<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(
        Request $request,
        AuditService $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim((string) $data['email']));

        $account = User::query()
            ->where('email', $email)
            ->first();

        /*
         * Always use the same public response for unknown, inactive,
         * suspended, or unverified accounts. This avoids exposing which
         * institutional email addresses have an account.
         */
        $genericMessage =
            'If an eligible SPMU-ACPMP account matches that email, password reset instructions have been sent.';

        if (
            ! $account
            || $account->account_status !== AccountStatus::Active
            || ! $account->hasVerifiedInstitutionalEmail()
        ) {
            return back()
                ->with('status', $genericMessage)
                ->withInput(['email' => $email]);
        }

        try {
            $status = Password::sendResetLink([
                'email' => $account->email,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $audit->record(
                'PASSWORD_RESET_DELIVERY_FAILED',
                $account,
                reason: 'Password reset email delivery failed.',
                after: [
                    'email' => $account->email,
                ],
            );

            return back()
                ->withErrors([
                    'email' => 'Password reset instructions could not be sent right now. Please contact ICTU for assistance.',
                ])
                ->withInput(['email' => $email]);
        }

        if ($status === Password::RESET_LINK_SENT) {
            $audit->record(
                'PASSWORD_RESET_REQUESTED',
                $account,
                after: [
                    'email' => $account->email,
                ],
            );
        }

        return back()
            ->with('status', $genericMessage)
            ->withInput(['email' => $email]);
    }

    public function resetForm(
        Request $request,
        string $token,
    ): View {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(
        Request $request,
        AuditService $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12)
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $account = User::query()
            ->where('email', strtolower(trim((string) $data['email'])))
            ->first();

        if (! $account) {
            return back()
                ->withErrors([
                    'email' => 'This password reset request is invalid or has expired.',
                ])
                ->withInput($request->only('email'));
        }

        if ($account->account_status !== AccountStatus::Active) {
            return back()
                ->withErrors([
                    'email' => 'This account is not active. Contact ICTU for assistance.',
                ])
                ->withInput($request->only('email'));
        }

        if (! $account->hasVerifiedInstitutionalEmail()) {
            return back()
                ->withErrors([
                    'email' => 'Verify your CSPC institutional email before resetting the password.',
                ])
                ->withInput($request->only('email'));
        }

        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (User $user, string $password) use ($audit): void {
                $user->forceFill([
                    'password' => $password,
                ]);

                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordResetEvent($user));

                $audit->record(
                    'PASSWORD_RESET_COMPLETED',
                    $user,
                    after: [
                        'password_reset_at' => now()->toIso8601String(),
                    ],
                );
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with(
                    'status',
                    'Your password has been reset. You may now sign in using your new password.'
                );
        }

        return back()
            ->withErrors([
                'email' => 'This password reset link is invalid or has expired. Request a new one.',
            ])
            ->withInput($request->only('email'));
    }
}