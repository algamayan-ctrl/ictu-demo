<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditService
{
    private static ?bool $storesActorSnapshot = null;

    public function record(string $action, Model|string $record, ?int $recordId = null, ?string $reason = null, mixed $before = null, mixed $after = null, ?string $correlationId = null): AuditEvent
    {
        $attributes = [
            'actor_user_id' => auth()->id(),
            'action_code' => $action,
            'record_type' => $record instanceof Model ? $record::class : $record,
            'record_id' => $record instanceof Model ? $record->getKey() : $recordId,
            'occurred_at' => now(),
            'origin_ip' => request()?->ip(),
            'reason' => $reason,
            'before_json' => $before,
            'after_json' => $after,
            'correlation_id' => $correlationId ?: (string) Str::uuid(),
        ];

        /*
         * Preserve who the actor was at the time of the action, so a later
         * rename or role change does not alter how this event reads. Only the
         * name and access classification are kept; never credentials.
         */
        $actor = auth()->user();

        if ($actor && $this->storesActorSnapshot()) {
            $role = $actor->getRawOriginal('access_classification');

            $attributes['actor_name'] = filled($actor->full_name) ? (string) $actor->full_name : null;
            $attributes['actor_role'] = filled($role) ? strtoupper((string) $role) : null;
        }

        return AuditEvent::query()->create($attributes);
    }

    /*
     * Audit writes must keep working on a database where the additive
     * actor-snapshot migration has not been run yet.
     */
    private function storesActorSnapshot(): bool
    {
        return self::$storesActorSnapshot ??= Schema::hasColumn('audit_events', 'actor_name')
            && Schema::hasColumn('audit_events', 'actor_role');
    }
}
