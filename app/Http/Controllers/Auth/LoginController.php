<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Auth\Concerns\EstablishesAuthenticatedSession;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        /*
         * LOGIN CREDENTIAL DIAGNOSTICS START
         *
         * Validate the password separately before the final authenticated
         * session is created so the login form can show a useful message
         * under the Password field. Unknown emails still receive a generic
         * response.
         */
        $account = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $account) {
            return back()
                ->withErrors([
                    'password' => 'Incorrect email or password. Please try again.',
                ])
                ->onlyInput('email');
        }

        if (! Hash::check($credentials['password'], $account->password)) {
            return back()
                ->withErrors([
                    'password' => 'Incorrect email or password. Please try again.',
                ])
                ->onlyInput('email');
        }

        if ($account->account_status !== AccountStatus::Active) {
            $statusMessage = $account->account_status === AccountStatus::Suspended
                ? 'This account is suspended. Contact the SPMU Admin for assistance.'
                : 'This account is inactive. Contact the SPMU Admin for assistance.';

            return back()
                ->withErrors([
                    'password' => $statusMessage,
                ])
                ->onlyInput('email');
        }

        /* LOGIN CREDENTIAL DIAGNOSTICS END */
        $credentials['account_status'] = AccountStatus::Active->value;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'password' => 'The supplied credentials are invalid or the account is not active.',
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
                    'Your account is pending verification. Enter the 6-digit OTP sent to your registered email address.'
                );
        }

        $error = $this->establishAuthenticatedSession($request, $user);

        if ($error !== null) {
            return back()
                ->withErrors(['password' => $error])
                ->onlyInput('email');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(
        Request $request,
        AuditService $audit,
    ): RedirectResponse {
        $user = $request->user();

        $idleLogout = $request->string('reason')->toString() === 'idle';

        if ($idleLogout && $user) {
            $timeoutMinutes = max(
                1,
                (int) config('session.idle_timeout', 15)
            );

            $audit->record(
                'SESSION_IDLE_TIMEOUT',
                $user,
                reason: "Authenticated session ended after {$timeoutMinutes} minutes of inactivity.",
                after: [
                    'idle_timeout_minutes' => $timeoutMinutes,
                ],
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($idleLogout) {
            $timeoutMinutes = max(
                1,
                (int) config('session.idle_timeout', 15)
            );

            return redirect()
                ->route('login')
                ->with(
                    'status',
                    "Your session ended after {$timeoutMinutes} minutes of inactivity. Please sign in again."
                );
        }

        return redirect()->route('home');
    }
}
