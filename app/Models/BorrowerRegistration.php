<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowerRegistration extends Model
{
    protected $fillable = ['user_id', 'registration_type', 'company_name', 'valid_id_file_id', 'status'];

    protected function casts(): array
    {
        return [
            'registration_type' => RegistrationType::class,
            'status' => RegistrationStatus::class,
            'reviewed_at' => 'datetime',
            'submitted_for_review_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function validIdFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'valid_id_file_id');
    }
}
