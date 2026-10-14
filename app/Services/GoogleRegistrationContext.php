<?php

namespace App\Services;

use App\Enums\RegistrationType;
use Illuminate\Http\Request;

/** Short-lived onboarding proof. Provider tokens and passwords are never stored. */
class GoogleRegistrationContext
{
    public function remember(Request $request, string $email, string $subject, ?string $name): void
    {
        $request->session()->regenerate();
        $request->session()->put('google_registration', [
            'email' => $email,
            'subject' => $subject,
            'name' => $name,
            // Google onboarding is CSPC Employee only (INSTITUTIONAL_EMAIL_DOMAINS); the callback refuses everyone else.
            'registration_type' => RegistrationType::Employee->value,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
    }

    public function current(Request $request): ?array
    {
        $context = $request->session()->get('google_registration');
        if (! is_array($context) || blank($context['subject'] ?? null)
            || ! filter_var($context['email'] ?? '', FILTER_VALIDATE_EMAIL)
            || (int) ($context['expires_at'] ?? 0) <= now()->timestamp
            || ($context['registration_type'] ?? '') !== RegistrationType::Employee->value
            || ! app(EmailVerificationService::class)->domainAllowed((string) $context['email'])) {
            $request->session()->forget('google_registration');
            return null;
        }

        return $context;
    }
}
