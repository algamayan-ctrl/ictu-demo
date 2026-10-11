<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportFormTemplate extends Model
{
    protected $fillable = [
        'report_key',
        'version_no',
        'status',
        'form_code',
        'effectivity_date',
        'revision_label',
        'title_template',
        'configuration_json',
        'change_reason',
        'configured_by_user_id',
        'activated_at',
    ];

    /**
     * Active and Archived revisions are approved history. Only a Draft is
     * editable; the single permitted change to an Active revision is its
     * supersession to Archived when a newer Draft is activated.
     */
    protected static function booted(): void
    {
        static::updating(function (ReportFormTemplate $template): void {
            $original = $template->getOriginal('status');

            if ($original === 'DRAFT') {
                return;
            }

            $changed = array_diff(array_keys($template->getDirty()), ['status', 'updated_at']);
            $archiving = $original === 'ACTIVE' && $template->status === 'ARCHIVED';

            if ($changed !== [] || ! $archiving) {
                throw new \LogicException('Report form revision '.$template->version_no.' is '.$original.' and cannot be modified.');
            }
        });

        static::deleting(function (ReportFormTemplate $template): void {
            if ($template->getOriginal('status') !== 'DRAFT') {
                throw new \LogicException('Only a Draft report form revision may be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'effectivity_date' => 'date',
            'configuration_json' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'configured_by_user_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }
}