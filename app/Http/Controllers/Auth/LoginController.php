<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Auth\Concerns\EstablishesAuthenticatedSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    use EstablishesAuthenticatedSession;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['account_status'] = AccountStatus::Active->value;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'The supplied credentials are invalid or the account is not active.',
            ])->onlyInput('email');
        }

        $user = $request->user();

        /*
         * Correct credentials prove that this is the local account holder, but
         * an unverified institutional mailbox must still complete OTP
         * verification before an authenticated SPMU-ACPMP session is allowed.
         *
         * End the temporary login session, then send the user directly to the
         * public OTP page with the ICTU-registered email prefilled.
         */
        if ($user && ! $user->hasVerifiedInstitutionalEmail()) {
            $email = (string) $user->email;

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('account.verification.show', ['email' => $email])
                ->with(
                    'status',
                    'Your account is pending verification. Enter the 6-digit OTP sent to your CSPC email address.'
                );
        }

        $error = $this->establishAuthenticatedSession($request, $user);

        if ($error !== null) {
            return back()
                ->withErrors(['email' => $error])
                ->onlyInput('email');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}