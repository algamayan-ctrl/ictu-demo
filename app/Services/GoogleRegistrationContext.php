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
            'registration_type' => str_ends_with($email, '@cspc.edu.ph')
                ? RegistrationType::Employee->value : RegistrationType::External->value,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
    }

    public function current(Request $request): ?array
    {
        $context = $request->session()->get('google_registration');
        if (! is_array($context) || blank($context['subject'] ?? null)
            || ! filter_var($context['email'] ?? '', FILTER_VALIDATE_EMAIL)
            || (int) ($context['expires_at'] ?? 0) <= now()->timestamp
            || ! in_array($context['registration_type'] ?? '', ['EMPLOYEE', 'EXTERNAL'], true)) {
            $request->session()->forget('google_registration');
            return null;
        }

        return $context;
    }
}
