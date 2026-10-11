<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $fillable = ['notification_event_id', 'template_id', 'recipient_user_id', 'channel', 'address_snapshot', 'attempt_no', 'provider', 'attempted_at', 'delivery_status', 'provider_response', 'read_at', 'resolved_at', 'resolved_by_user_id', 'resolution_note'];

    /**
     * Channels the dispatcher (NotificationService::send()) can currently
     * attempt. SMS was removed from delivery entirely (no dispatch branch,
     * no provider config); historical channel=SMS rows are audit-only
     * "never attempted, no provider configured" records, not real
     * delivery-provider outcomes.
     */
    public const OPERATIONAL_CHANNELS = ['SYSTEM', 'EMAIL'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime', 'read_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NotificationEvent::class, 'notification_event_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * Transport or provider text made safe to store and to show in delivery
     * diagnostics: the host, port and error stay readable, while account
     * names, passwords, tokens and embedded credentials are hidden. Applied
     * when a failure is recorded and again when any record is displayed, so
     * older rows are covered without being rewritten.
     */
    public static function sanitizeDiagnostic(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        $text = (string) preg_replace(
            [
                '#(://)[^/\s:@]+:[^/\s@]+@#',
                '/\b(user(?:name)?|login)(\s*[=:]\s*|(?<=username)\s+(?="))("?)[^"\s,;]+\3/i',
                '/\b(pass(?:word|wd)?|pwd|secret|token|api[_-]?key|client[_-]?secret)(\s*[=:]\s*)("?)[^"\s,;]+\3/i',
                '/\b(authorization\s*:\s*(?:bearer\s+|basic\s+)?)[A-Za-z0-9+\/._=-]{8,}/i',
                '/\b(AUTH\s+(?:LOGIN|PLAIN|XOAUTH2|CRAM-MD5)\s+)\S+/i',
                '/(?<![A-Za-z0-9+\/_-])[A-Za-z0-9+\/_-]{40,}={0,2}/',
            ],
            [
                '$1[hidden]@',
                '$1$2$3[hidden]$3',
                '$1$2$3[hidden]$3',
                '$1[hidden]',
                '$1[hidden]',
                '[hidden]',
            ],
            $text
        );

        return mb_substr($text, 0, 1000);
    }

    /**
     * A short, readable summary of a failure, decided only from wording the
     * transport itself reported. Anything that does not clearly match a
     * known kind gets the generic summary; nothing is inferred beyond the
     * text. Always computed from the sanitized diagnostic.
     *
     * @return array{title: string, explanation: string}
     */
    public static function failureSummary(?string $diagnostic): array
    {
        $text = mb_strtolower(self::sanitizeDiagnostic($diagnostic));

        $code = preg_match(
            '/(?:\bcode\s+"?|\bsmtp\s+|\bmessage\s+"?)([45]\d{2})\b|\b([45]\d{2})[ -]\d\.\d{1,3}\.\d{1,3}\b/',
            $text,
            $match
        ) === 1 ? ($match[1] !== '' ? $match[1] : ($match[2] ?? '')) : '';
        $withCode = static fn (string $title): string => $code !== '' ? "{$title} ({$code})" : $title;

        $has = static function (array $needles) use ($text): bool {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return true;
                }
            }

            return false;
        };

        return match (true) {
            $text === '' => [
                'title' => 'Email Delivery Failed',
                'explanation' => 'The attempt failed, but the mail transport did not report a reason.',
            ],
            $has(['recipient address is not configured']) => [
                'title' => 'Recipient Address Missing',
                'explanation' => 'The recipient had no email address on record when this notification was attempted.',
            ],
            $has(['failed to authenticate', 'authentication failed', 'authentication unsuccessful', 'bad credentials', 'username and password not accepted', 'authentication required']) => [
                'title' => $withCode('SMTP Authentication Failed'),
                'explanation' => 'The mail server rejected the sign-in of the system\'s sending account. Check that account\'s credentials and the mail provider\'s sign-in requirements.',
            ],
            $has(['getaddrinfo', 'name or service not known', 'no address associated with hostname', 'temporary failure in name resolution', 'could not resolve host']) => [
                'title' => 'DNS Resolution Failed',
                'explanation' => 'The mail server\'s host name could not be resolved. Check the server\'s network and DNS, and the configured mail host.',
            ],
            $has(['starttls', 'unable to connect with tls', 'ssl operation failed', 'certificate verify failed', 'crypto', 'tls handshake', 'ssl handshake']) => [
                'title' => 'TLS Connection Failed',
                'explanation' => 'A secure connection to the mail server could not be established. Check the encryption setting, the port, and the server\'s certificate.',
            ],
            $has(['mailbox unavailable', 'user unknown', 'recipient address rejected', 'no such user', 'mailbox not found']) => [
                'title' => $withCode('Recipient Address Rejected'),
                'explanation' => 'The mail server did not accept the recipient\'s address. Confirm that the address on the account is correct and active.',
            ],
            $has(['connection could not be established', 'connection refused', 'connection timed out', 'timed out', 'has been closed unexpectedly', 'network is unreachable', 'unable to connect', 'no route to host']) => [
                'title' => 'Mail Server Connection Failed',
                'explanation' => 'The mail server could not be reached, or it closed the connection. Check that the server is reachable and that the host and port are correct.',
            ],
            default => [
                'title' => $withCode('Email Delivery Failed'),
                'explanation' => 'The mail transport reported an error that does not match a known type. Review the technical details.',
            ],
        };
    }

    /**
     * True only when an explicit resolution has been recorded
     * (resolved_at is set). Never inferred from age - a FAILED row stays
     * unresolved indefinitely until ICTU explicitly resolves it, or a
     * provably-linked retry succeeds (neither of which this model does on
     * its own).
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Failures that are currently actionable for ICTU technical review:
     * excludes historical channel=SMS failures (SMS has no dispatch path
     * today - those rows reflect a provider that was never configured, not
     * a genuine delivery rejection) and excludes failures already marked
     * resolved. Used identically by the dashboard KPI and its drilldown so
     * the counted number and the listed rows never diverge. A resolved row
     * keeps delivery_status = FAILED for audit; only resolved_at changes.
     */
    public function scopeActionableFailures(Builder $query): Builder
    {
        return $query->where('delivery_status', 'FAILED')
            ->whereIn('channel', self::OPERATIONAL_CHANNELS)
            ->whereNull('resolved_at');
    }
}
