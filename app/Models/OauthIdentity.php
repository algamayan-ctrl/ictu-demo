<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OauthIdentity extends Model
{
    protected $fillable = ['user_id', 'provider', 'subject'];

    protected $hidden = ['subject'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
