<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    protected $fillable = ['actor_user_id', 'actor_name', 'actor_role', 'action_code', 'record_type', 'record_id', 'occurred_at', 'origin_ip', 'reason', 'before_json', 'after_json', 'correlation_id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'before_json' => 'array', 'after_json' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
