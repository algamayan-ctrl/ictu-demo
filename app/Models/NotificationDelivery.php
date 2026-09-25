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
