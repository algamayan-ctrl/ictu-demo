<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Auth\Concerns\EstablishesAuthenticatedSession;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OauthIdentity;
use App\Services\AuditService;
use App\Services\GoogleRegistrationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Google sign-in.
 *
 * Google proves who the person is. SPMU-ACPMP decides whether that person may
 * enter. Known identities use their linked local account. New identities may
 * complete Borrower registration, with email locked and SAO approval required.
 */
class GoogleAuthController extends Controller
{
    use EstablishesAuthenticatedSession;

    /**
     * Send the visitor to Google's consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->forget('google_registration');
        if (! $this->isConfigured()) {
            return redirect()->route('home')->withErrors([
                'google' => 'Google sign-in is not configured on this server. Use your email and password.',
            ]);
        }

        try {
            $driver = Socialite::driver('google')->scopes(['openid', 'email', 'profile']);

            /*
             * The hosted-domain parameter only pre-filters Google's own account
             * chooser. It is a convenience, never the security check - the
             * returned address is still validated server-side below.
             */
            $hint = $this->allowedDomains()[0] ?? null;
            $parameters = ['prompt' => 'select_account'];

            if ($hint !== null) {
                $parameters['hd'] = $hint;
            }
            $driver->with($parameters);

            return $driver->redirect();
        } catch (Throwable $exception) {
            return $this->fail($exception, 'Google sign-in is unavailable right now. Use your email and password.');
        }
    }

    public function link(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->string('password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Enter your current password to link Google.']);
        }
        $request->session()->put('google_link', ['user_id' => $request->user()->id, 'expires_at' => now()->addMinutes(10)->timestamp]);

        return $this->redirect($request);
    }

    /**
     * Handle Google's callback and sign in the matching local account.
     */
    public function callback(Request $request): RedirectResponse
    {
        $request->session()->forget('google_registration');
        if (! $this->isConfigured()) {
            return redirect()->route('home')->withErrors([
                'google' => 'Google sign-in is not configured on this server. Use your email and password.',
            ]);
        }

        /* The visitor dismissed Google's consent screen. */
        if ($request->filled('error')) {
            return redirect()->route('home')->withErrors([
                'google' => 'Google sign-in was cancelled. You can try again or use your email and password.',
            ]);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            return $this->fail($exception, 'Google sign-in could not be completed. Please try again.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));

        $verified = $googleUser->getRaw()['email_verified'] ?? null;
        if ($verified !== true || trim((string) $googleUser->getId()) === '') {
            return redirect()->route('home')->withErrors([
                'google' => 'Google could not provide a verified identity. Use your email and password.',
            ]);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('home')->withErrors([
                'google' => 'Google did not return an email address for this account. Use your email and password.',
            ]);
        }

        if (! $this->domainAllowed($email)) {
            return redirect()->route('home')->withErrors([
                'google' => 'This Google account is not from an allowed domain. Contact ICTU for account access assistance.',
            ]);
        }

        $link = $request->session()->pull('google_link');
        if ($link !== null || $request->user() !== null) {
            $user = $request->user();
            if (! is_array($link) || ! $user || (int) ($link['user_id'] ?? 0) !== $user->id
                || (int) ($link['expires_at'] ?? 0) < now()->timestamp
                || $user->account_status !== AccountStatus::Active || ! $user->hasVerifiedInstitutionalEmail()
                || ! $user->hasApprovedRegistration() || strtolower($user->email) !== $email) {
                return redirect()->route('profile.show')->withErrors(['google' => 'Google linking requires your active, verified account and its matching email. Sign in and try again.']);
            }
            try {
                DB::transaction(function () use ($user, $googleUser): void {
                    $identity = OauthIdentity::query()->where('provider', 'google')->where('subject', $googleUser->getId())->first();
                    $existing = OauthIdentity::query()->where('user_id', $user->id)->where('provider', 'google')->first();
                    if (($identity && $identity->user_id !== $user->id) || ($existing && $existing->subject !== $googleUser->getId())) {
                        throw ValidationException::withMessages(['google' => 'This Google identity is already linked. No account was changed.']);
                    }
                    if (! $identity) {
                        OauthIdentity::query()->create(['user_id' => $user->id, 'provider' => 'google', 'subject' => $googleUser->getId()]);
                        app(AuditService::class)->record('GOOGLE_ACCOUNT_LINKED', $user, after: ['provider' => 'google']);
                    }
                });
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['google' => 'This Google identity was already linked. No account was changed.']);
            }

            return redirect()->route('profile.show')->with('success', 'Google sign-in linked to your account.');
        }

        // A CSPC Google identity alone never proves employee borrowing eligibility.
        $user = OauthIdentity::query()->where('provider', 'google')->where('subject', $googleUser->getId())->first()?->user;

        if (! $user) {
            if (! User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                app(GoogleRegistrationContext::class)->remember($request, $email,
                    trim((string) $googleUser->getId()), $googleUser->getName());

                return redirect()->route('register', ['source' => 'google']);
            }
            return redirect()->route('home')->withErrors([
                'google' => 'This Google account is not registered or linked in SPMU-ACPMP. Sign in with your email and password, then link Google in Account Settings. New Borrowers must register and await SAO approval.',
            ]);
        }

        /* The same account-status rule the credential login applies. */
        if ($user->account_status !== AccountStatus::Active) {
            return redirect()->route('home')->withErrors([
                'google' => 'This account is inactive. Contact ICTU if you believe this is an error.',
            ]);
        }

        Auth::login($user);

        $error = $this->establishAuthenticatedSession($request, $user);

        if ($error !== null) {
            return redirect()->route('home')->withErrors(['google' => $error]);
        }

        app(AuditService::class)->record('GOOGLE_LOGIN', $user, after: ['provider' => 'google']);

        return redirect()->intended(route('dashboard'));
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    /**
     * Optional allow-list, comma separated. Blank means every Google account
     * may reach the local user lookup, which still decides access.
     *
     * @return list<string>
     */
    private function allowedDomains(): array
    {
        return collect(explode(',', (string) config('services.google.allowed_domains')))
            ->map(fn (string $domain) => strtolower(trim(ltrim($domain, '@'))))
            ->filter()
            ->values()
            ->all();
    }

    private function domainAllowed(string $email): bool
    {
        $domains = $this->allowedDomains();

        if ($domains === []) {
            return true;
        }

        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return $domain !== '' && in_array($domain, $domains, true);
    }

    /**
     * Log the real cause for ICTU and show the visitor a plain message. OAuth
     * secrets, tokens and stack traces never reach the response.
     */
    private function fail(Throwable $exception, string $message): RedirectResponse
    {
        Log::warning('Google sign-in failed.', [
            'exception' => $exception::class,
        ]);

        return redirect()->route('home')->withErrors(['google' => $message]);
    }
}
