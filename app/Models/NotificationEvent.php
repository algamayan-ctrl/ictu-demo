<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationEvent extends Model
{
    /**
     * Reminders the scheduler issues once per custody: it checks only that
     * the event exists, so a reminder whose email failed is not attempted
     * again. Delivery Records states this instead of implying a retry.
     */
    public const SCHEDULED_ONCE_ONLY = ['RETURN_DUE_TODAY', 'RETURN_DUE_TOMORROW', 'BORROWING_OVERDUE'];

    protected $fillable = ['event_code', 'source_type', 'source_id', 'created_by_user_id', 'payload_snapshot_json', 'occurred_at'];

    protected function casts(): array
    {
        return ['payload_snapshot_json' => 'array', 'occurred_at' => 'datetime'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
