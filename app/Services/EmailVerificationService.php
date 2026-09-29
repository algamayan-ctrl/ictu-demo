<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Handles verification of the institutional email stored on a local account.
 *
 * Account registration by ICTU creates the local identity only. Ownership of
 * the email address is proven separately when the recipient opens a temporary
 * signed link delivered to that mailbox.
 */
class EmailVerificationService
{
    private const VERIFICATION_LINK_HOURS = 24;

    /**
     * Send a temporary signed verification link to the user's current email.
     *
     * A mail transport accepting the message is not treated as proof that the
     * mailbox exists. The account becomes verified only after the signed link
     * is opened successfully by the recipient.
     */
    public function send(User $user): bool
    {
        if ($user->hasVerifiedInstitutionalEmail()) {
            return true;
        }

        $verificationUrl = URL::temporarySignedRoute(
            'email.verification.verify',
            now()->addHours(self::VERIFICATION_LINK_HOURS),
            [
                'user' => $user->getKey(),
                // Binding the current email to the link automatically makes an
                // older link unusable after ICTU changes the account email.
                'hash' => $this->emailHash((string) $user->email),
            ],
        );

        $recipientName = e($user->full_name ?: 'CSPC User');
        $safeUrl = e($verificationUrl);
        $expiration = self::VERIFICATION_LINK_HOURS;

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verify your CSPC email</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#172033;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:680px;background:#ffffff;border:1px solid #dde6f0;border-radius:12px;">
                    <tr>
                        <td style="padding:28px 32px;border-bottom:3px solid #0b2854;">
                            <div style="font-size:26px;font-weight:800;color:#0b2854;">SPMU-ACPMP</div>
                            <div style="margin-top:5px;font-size:14px;color:#486581;">Camarines Sur Polytechnic Colleges</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 32px;">
                            <h1 style="margin:0 0 18px;font-size:24px;color:#102a43;">Verify your CSPC email address</h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">Dear {$recipientName},</p>
                            <p style="margin:0 0 22px;font-size:15px;line-height:1.7;">
                                ICTU registered an SPMU-ACPMP account using this email address. Confirm that you can receive mail at this address before the account is allowed to enter the system.
                            </p>
                            <p style="margin:0 0 24px;">
                                <a href="{$safeUrl}" style="display:inline-block;padding:12px 18px;background:#1769e0;color:#ffffff;text-decoration:none;font-weight:700;border-radius:8px;">Verify CSPC Email</a>
                            </p>
                            <p style="margin:0 0 12px;font-size:14px;line-height:1.65;color:#486581;">
                                This verification link expires in {$expiration} hours and is valid only for the email address currently recorded by ICTU.
                            </p>
                            <p style="margin:0;font-size:14px;line-height:1.65;color:#486581;">
                                If you were not expecting this account, do not open the link and contact ICTU.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        try {
            Mail::html($html, function (Message $mail) use ($user): void {
                $mail
                    ->to((string) $user->email)
                    ->subject('[SPMU-ACPMP] Verify your CSPC email address');
            });

            Log::info('SPMU-ACPMP institutional email verification message accepted by mail transport.', [
                'user_id' => $user->getKey(),
                'email' => $user->email,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('SPMU-ACPMP institutional email verification delivery failed.', [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Hash used inside the signed URL so changing the stored email invalidates
     * every verification link that was generated for the previous address.
     */
    public function emailHash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /**
     * Check the institution-level domain allow-list used by ICTU registration.
     */
    public function domainAllowed(string $email): bool
    {
        $allowedDomains = $this->allowedDomains();

        if ($allowedDomains === []) {
            // A missing policy must not permit arbitrary personal domains.
            return false;
        }

        $domain = strtolower((string) substr(strrchr(trim($email), '@') ?: '', 1));

        return $domain !== '' && in_array($domain, $allowedDomains, true);
    }

    /** @return list<string> */
    public function allowedDomains(): array
    {
        return collect(explode(',', (string) config('services.institutional_email.allowed_domains')))
            ->map(fn (string $domain) => strtolower(trim(ltrim($domain, '@'))))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
