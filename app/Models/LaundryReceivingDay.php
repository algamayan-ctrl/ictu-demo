<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A date on which the SPMU Head/Admin authorized the Laundry Area to receive
 * returned linen. It is independent of the SPMU Operational Calendar: it
 * does not open the SPMU Office and applies to linen only.
 *
 * A row is never deleted. Revoking keeps the row (is_authorized = false)
 * with who revoked it, when and why.
 */
class LaundryReceivingDay extends Model
{
    protected $fillable = [
        'receiving_date',
        'is_authorized',
        'reason',
        'authorized_by_user_id',
        'authorized_at',
        'revocation_reason',
        'revoked_by_user_id',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'receiving_date' => 'date',
            'is_authorized' => 'boolean',
            'authorized_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
