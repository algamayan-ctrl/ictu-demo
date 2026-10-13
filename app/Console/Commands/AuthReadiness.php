<?php

namespace App\Console\Commands;

use App\Services\AuthMailTransport;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AuthReadiness extends Command
{
    protected $signature = 'spmu:auth-readiness {--json : Show non-secret checks} {--check-smtp : Check SMTP connection/authentication without sending email} {--send-test-to= : Explicitly send a diagnostic email to this address}';
    protected $description = 'Check Google/email configuration; sending a diagnostic requires an explicit recipient';

    public function handle(): int
    {
        $checks = [
            'Google client configured' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
            'Exact application callback' => config('services.google.redirect') === rtrim((string) config('app.url'), '/').'/auth/google/callback',
            'Delivery-capable mail transport' => app(AuthMailTransport::class)->deliversMail(),
            'Valid sender address' => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false,
            'Persistent OAuth session' => in_array(config('session.driver'), ['file', 'database', 'redis'], true),
            'External Google accounts allowed' => blank(config('services.google.allowed_domains')),
        ];
        $smtp = 'NOT TESTED';
        if ($this->option('check-smtp')) {
            $transport = null;
            try {
                if (config('mail.mailers.'.config('mail.default').'.transport') !== 'smtp') {
                    throw new \RuntimeException('SMTP transport required.');
                }
                $transport = Mail::mailer()->getSymfonyTransport();
                $transport->start();
                $checks['SMTP connection/authentication'] = true;
                $smtp = 'ACCEPTED; NO EMAIL SENT';
            } catch (Throwable $exception) {
                $checks['SMTP connection/authentication'] = false;
                $smtp = 'FAILED: '.$exception::class;
            } finally {
                try { $transport?->stop(); } catch (Throwable) { /* No credentials in diagnostics. */ }
            }
        }
        $delivery = 'NOT TESTED';
        if ($recipient = $this->option('send-test-to')) {
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL) || ! $checks['Delivery-capable mail transport']) {
                $this->error('Choose a valid test recipient and configure a real email transport first.');
                return self::FAILURE;
            }
            try {
                Mail::html('<p>SPMU-ACPMP email configuration test. This message is not an OTP, account approval, or password reset.</p>',
                    fn (Message $mail) => $mail->to($recipient)->subject('[SPMU-ACPMP] Email configuration test'));
                $delivery = 'ACCEPTED BY TRANSPORT; INBOX RECEIPT MUST BE CONFIRMED';
            } catch (Throwable $exception) {
                $delivery = 'FAILED: '.$exception::class;
            }
        }
        $result = ['scope' => 'configuration only', 'gate' => in_array(false, $checks, true) ? 'NOT READY' : 'PASS',
            'checks' => $checks, 'smtp_test' => $smtp, 'email_test' => $delivery,
            'not_verified' => ['Real Google consent/callback', 'Actual OTP and reset email receipt', 'Production hosting and ICTU approval']];
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $result['gate'] === 'PASS' && ! str_starts_with($delivery, 'FAILED') ? self::SUCCESS : self::FAILURE;
    }
}
