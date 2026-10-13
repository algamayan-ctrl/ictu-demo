<?php

namespace App\Services;

/** Prevent preview-only transports from claiming a real OTP/reset email was sent. */
class AuthMailTransport
{
    public function deliversMail(): bool
    {
        return $this->supports((string) config('mail.default'), []);
    }

    private function supports(string $mailer, array $visited): bool
    {
        if (in_array($mailer, $visited, true)) return false;
        $visited[] = $mailer;
        $settings = config('mail.mailers.'.$mailer, []);
        $transport = $settings['transport'] ?? '';
        if (in_array($transport, ['array', 'log'], true)) {
            return app()->environment('testing');
        }
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $settings['mailers'] ?? [];
            return $children !== [] && collect($children)->every(fn ($child) => $this->supports($child, $visited));
        }
        return in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true);
    }
}
