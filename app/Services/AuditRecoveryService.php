<?php

namespace App\Services;

use App\Enums\AccessClassification;
use App\Http\Controllers\SettingController;
use App\Models\AuditEvent;
use App\Models\InventoryItem;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Controlled "Revert This Change" for the few record changes that are safe to
 * put back directly from an audit event.
 *
 * Only the fields listed here can be restored. Stock, custody, returns,
 * penalties, payments, request revisions, calendar rules, account access and
 * credentials are deliberately absent: they keep their own correction
 * workflows. A revert never edits or removes an audit event; it writes a new
 * one that points at the event it reverted.
 */
class AuditRecoveryService
{
    /** Original action code => action code written by its revert. */
    public const REVERT_ACTIONS = [
        'INVENTORY_ITEM_UPDATED' => 'INVENTORY_ITEM_CHANGE_REVERTED',
        'SYSTEM_SETTING_CHANGED' => 'SYSTEM_SETTING_CHANGE_REVERTED',
        'USER_ACCOUNT_UPDATED' => 'USER_ACCOUNT_CHANGE_REVERTED',
    ];

    private const MODULES = [
        'INVENTORY_ITEM_UPDATED' => InventoryItem::class,
        'SYSTEM_SETTING_CHANGED' => SystemSetting::class,
        'USER_ACCOUNT_UPDATED' => User::class,
    ];

    /*
     * The item's name and specification only. Category, unit, the borrowable,
     * laundry-required, provisional and off-campus flags, quantity, condition
     * and archive status keep their own validated screens and workflows.
     */
    private const INVENTORY_FIELDS = [
        'unique_description' => 'Item Name',
        'specification' => 'Specification',
    ];

    /*
     * Designation and mobile number only. Full name, email, employee number,
     * account status, access classification, roles, unit assignments,
     * verification and credentials are never restored from an audit event.
     */
    private const USER_FIELDS = [
        'designation' => 'Designation',
        'mobile_no' => 'Mobile No.',
    ];

    private const SETTING_FIELDS = [
        'value' => 'Value',
    ];

    private const IGNORED_SNAPSHOT_KEYS = ['id', 'created_at', 'updated_at', 'change_source', 'updated_by_user_id'];

    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * Attach a read-only recovery assessment to each event on one page.
     *
     * @param  Collection<int, AuditEvent>  $events
     */
    public function annotate(Collection $events, User $actor): void
    {
        $candidates = $events->filter(fn (AuditEvent $event): bool => $this->moduleFor($event) !== null);

        if ($candidates->isEmpty()) {
            return;
        }

        $records = [];
        foreach ($candidates->groupBy('record_type') as $recordType => $group) {
            $records[$recordType] = $recordType::query()
                ->whereIn('id', $group->pluck('record_id')->filter()->unique()->values())
                ->get()
                ->keyBy('id');
        }

        $reverts = $this->revertEventsFor($candidates);

        foreach ($candidates as $event) {
            $assessment = $this->assess(
                $event,
                $actor,
                $records[$event->record_type][$event->record_id] ?? null,
                $reverts[$event->id] ?? null
            );

            if ($assessment !== null) {
                $event->setAttribute('recovery', $assessment);
            }
        }
    }

    /**
     * Put the eligible fields of one audit event back to their previous
     * values. The record is locked and re-checked inside the transaction, so
     * a stale page or a second click cannot overwrite a newer change.
     */
    public function revert(AuditEvent $event, User $actor, string $reason): AuditEvent
    {
        $module = $this->moduleFor($event);

        if ($module === null) {
            throw $this->refuse('This audit event cannot be reverted from the Audit Trail.');
        }

        return DB::transaction(function () use ($event, $actor, $reason, $module): AuditEvent {
            $record = $module::query()->lockForUpdate()->find($event->record_id);

            $assessment = $this->assess(
                $event,
                $actor,
                $record,
                $this->revertEventsFor(collect([$event]))[$event->id] ?? null
            );

            if ($assessment === null) {
                abort(403, 'You are not authorized to revert this change.');
            }

            if (! $assessment['allowed']) {
                throw $this->refuse($assessment['blocker'] ?? 'This change can no longer be reverted.');
            }

            $restore = [];
            $before = [];
            foreach ($assessment['fields'] as $field) {
                $restore[$field['field']] = $field['restore_raw'];
                $before[$field['field']] = $field['current_raw'];
            }

            if ($record instanceof SystemSetting) {
                $record->update([
                    'value_json' => $restore['value'],
                    'updated_by_user_id' => $actor->id,
                ]);

                DB::table('configuration_changes')->insert([
                    'system_setting_id' => $record->id,
                    'changed_by_user_id' => $actor->id,
                    'before_value_json' => json_encode($before['value']),
                    'after_value_json' => json_encode($restore['value']),
                    'reason' => $reason,
                    'changed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Only the listed fields are written; nothing else on the record is touched.
                $record->update($restore);
            }

            return $this->audit->record(
                self::REVERT_ACTIONS[$event->action_code],
                $record,
                reason: $reason,
                before: $before,
                after: $restore + [
                    'reverted_audit_event_no' => $event->id,
                    'reverted_audit_event_recorded_at' => $event->occurred_at?->toIso8601String(),
                ]
            );
        }, 3);
    }

    /**
     * Null when this actor has no revert authority over the event's module,
     * so nothing is shown. Otherwise the fields that would be restored, or the
     * reason the revert is not available.
     *
     * @return array{allowed: bool, blocker: ?string, fields: list<array<string, mixed>>, skipped: list<string>, reverted_by: ?AuditEvent}|null
     */
    private function assess(AuditEvent $event, User $actor, ?Model $record, ?AuditEvent $revertedBy): ?array
    {
        $module = $this->moduleFor($event);

        if ($module === null || ! $this->authorized($module, $actor, $record)) {
            return null;
        }

        $result = [
            'allowed' => false,
            'blocker' => null,
            'fields' => [],
            'skipped' => [],
            'reverted_by' => $revertedBy,
        ];

        if ($revertedBy !== null) {
            $result['blocker'] = 'This change was already reverted.';

            return $result;
        }

        if ($record === null) {
            $result['blocker'] = 'The record no longer exists, so this change cannot be reverted.';

            return $result;
        }

        $before = is_array($event->before_json) ? $event->before_json : null;
        $after = is_array($event->after_json) ? $event->after_json : null;

        if ($before === null || $after === null) {
            $result['blocker'] = 'The previous values were not recorded for this event, so it cannot be reverted.';

            return $result;
        }

        $allowedFields = match ($module) {
            InventoryItem::class => self::INVENTORY_FIELDS,
            User::class => self::USER_FIELDS,
            default => self::SETTING_FIELDS,
        };

        $conflicts = [];

        foreach ($this->orderedSnapshotKeys($module, $before, $after) as $key) {
            if (in_array($key, self::IGNORED_SNAPSHOT_KEYS, true)) {
                continue;
            }

            // A value missing from either snapshot was not recorded; it is never guessed.
            $recorded = array_key_exists($key, $before) && array_key_exists($key, $after);

            if ($recorded && $this->same($before[$key], $after[$key])) {
                continue;
            }

            if (! $recorded || ! isset($allowedFields[$key])) {
                $result['skipped'][] = (string) Str::of($key)->replace('_', ' ')->headline();

                continue;
            }

            $current = $record instanceof SystemSetting
                ? $record->value_json
                : $record->getAttribute($key);

            if (! $this->same($current, $after[$key])) {
                $conflicts[] = $allowedFields[$key];
            }

            $result['fields'][] = [
                'field' => $key,
                'label' => $allowedFields[$key],
                'current_raw' => $current,
                'restore_raw' => $before[$key],
                'current' => $this->display($module, $key, $current),
                'restore' => $this->display($module, $key, $before[$key]),
            ];
        }

        if ($result['fields'] === []) {
            $result['blocker'] = 'This change affected only values that must be corrected through their own workflow.';

            return $result;
        }

        if ($conflicts !== []) {
            $result['blocker'] = 'This record was changed again after this event ('
                .implode(', ', $conflicts)
                .'). Reverting now would overwrite the newer change, so it is not allowed.';

            return $result;
        }

        $restore = collect($result['fields'])->pluck('restore_raw', 'field')->all();

        $result['blocker'] = match ($module) {
            InventoryItem::class => $this->inventoryBlocker($record, $restore),
            User::class => $this->userBlocker($record, $restore),
            default => $this->settingBlocker($record, $restore['value']),
        };

        $result['allowed'] = $result['blocker'] === null;

        return $result;
    }

    /**
     * The snapshot's field names in one fixed order.
     *
     * The order a snapshot's keys come back in belongs to the database, not
     * to the record: a text column returns them as written, a native JSON
     * column returns them re-sorted. Fields are therefore listed in the
     * record's own declared order (its model's fillable list), and any field
     * outside that list follows alphabetically, so the same event reads the
     * same everywhere.
     *
     * @param  class-string<Model>  $module
     * @param  array<int|string, mixed>  $before
     * @param  array<int|string, mixed>  $after
     * @return list<string>
     */
    private function orderedSnapshotKeys(string $module, array $before, array $after): array
    {
        $keys = array_values(array_unique(array_map(
            'strval',
            array_merge(array_keys($before), array_keys($after))
        )));

        $declared = array_flip((new $module)->getFillable());

        usort($keys, function (string $a, string $b) use ($declared): int {
            $aDeclared = isset($declared[$a]);
            $bDeclared = isset($declared[$b]);

            if ($aDeclared && $bDeclared) {
                return $declared[$a] <=> $declared[$b];
            }

            if ($aDeclared !== $bDeclared) {
                return $aDeclared ? -1 : 1;
            }

            return strcmp($a, $b);
        });

        return $keys;
    }

    /** @return class-string<Model>|null */
    private function moduleFor(AuditEvent $event): ?string
    {
        $module = self::MODULES[(string) $event->action_code] ?? null;

        return $module !== null
            && $event->record_type === $module
            && $event->record_id !== null
                ? $module
                : null;
    }

    /** The same role that may edit the record on its own screen. */
    private function authorized(string $module, User $actor, ?Model $record): bool
    {
        $classification = $actor->access_classification;

        if ($module === InventoryItem::class) {
            return $classification === AccessClassification::SpmuHead;
        }

        if ($module === User::class) {
            return $classification === AccessClassification::IctuMaintainer;
        }

        /*
         * Only ICTU's own technical settings. Operational settings such as
         * fees, sanctions and deadlines are never reverted from an audit
         * event; the SPMU Head changes them on the configuration screen.
         */
        return $classification === AccessClassification::IctuMaintainer
            && $record instanceof SystemSetting
            && in_array((string) $record->setting_key, SettingController::TECHNICAL_SETTING_KEYS, true)
            && ! in_array((string) $record->setting_key, SettingController::PROTECTED_DIRECT_SETTING_KEYS, true);
    }

    /** @param  array<string, mixed>  $restore */
    private function inventoryBlocker(InventoryItem $item, array $restore): ?string
    {
        $name = trim((string) ($restore['unique_description'] ?? $item->unique_description));

        if ($name === '' || mb_strlen($name) > 255) {
            return 'The previous item name is not valid under the current rules.';
        }

        $nameTaken = InventoryItem::query()
            ->where('category_id', $item->category_id)
            ->where('unique_description', $name)
            ->whereKeyNot($item->id)
            ->exists();

        if ($nameTaken) {
            return 'Another inventory item in that category already uses the previous name.';
        }

        if ($item->off_campus_allowed && strcasecmp($name, 'Barricade') !== 0) {
            return 'Current policy permits off-campus use only for Barricade, so the previous name cannot be restored while off-campus use is enabled.';
        }

        return null;
    }

    /** @param  array<string, mixed>  $restore */
    private function userBlocker(User $user, array $restore): ?string
    {
        if (array_key_exists('designation', $restore)) {
            $designation = trim((string) $restore['designation']);

            if ($designation === '' || mb_strlen($designation) > 255) {
                return 'The previous designation is not valid under the current rules.';
            }
        }

        if (array_key_exists('designation', $restore)
            && $user->access_classification === AccessClassification::BorrowerOnly
            && strcasecmp(trim((string) $restore['designation']), AccessClassification::BorrowerOnly->label()) === 0) {
            return 'The previous designation is not an official designation under the current rules.';
        }

        if (array_key_exists('mobile_no', $restore) && mb_strlen((string) $restore['mobile_no']) > 30) {
            return 'The previous mobile number is not valid under the current rules.';
        }

        return null;
    }

    /** The previous value must still pass the rules of the setting's own edit form. */
    private function settingBlocker(SystemSetting $setting, mixed $value): ?string
    {
        $valid = match (true) {
            $setting->setting_key === 'backup_schedule' => is_array($value)
                && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($value['time'] ?? '')) === 1
                && in_array($value['recurrence'] ?? null, ['daily', 'weekly', 'monthly'], true),
            $setting->setting_key === 'max_upload_mb' => is_int($value) && $value >= 1 && $value <= 64,
            $value === null => true,
            $setting->data_type === 'INTEGER' => is_int($value),
            $setting->data_type === 'MONEY' => is_int($value) || is_float($value),
            default => is_string($value) && mb_strlen($value) <= 2000,
        };

        return $valid
            ? null
            : 'The previous value is not valid under the current rules for this setting.';
    }

    /**
     * Revert events already written for the given events, keyed by the id of
     * the event they reverted.
     *
     * @param  Collection<int, AuditEvent>  $events
     * @return array<int, AuditEvent>
     */
    private function revertEventsFor(Collection $events): array
    {
        $recordIds = $events->pluck('record_id')->filter()->unique()->values();

        if ($recordIds->isEmpty()) {
            return [];
        }

        $map = [];

        AuditEvent::query()
            ->whereIn('action_code', array_values(self::REVERT_ACTIONS))
            ->whereIn('record_id', $recordIds)
            ->orderBy('id')
            ->get()
            ->each(function (AuditEvent $revert) use (&$map): void {
                $revertedNo = is_array($revert->after_json)
                    ? ($revert->after_json['reverted_audit_event_no'] ?? null)
                    : null;

                if (is_numeric($revertedNo)) {
                    $map[(int) $revertedNo] ??= $revert;
                }
            });

        return $map;
    }

    private function same(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return is_array($a) && is_array($b) && $this->canonical($a) === $this->canonical($b);
        }

        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return trim((string) ($a ?? '')) === trim((string) ($b ?? ''));
    }

    private function canonical(array $value): string
    {
        $sort = function (array &$items) use (&$sort): void {
            foreach ($items as &$item) {
                if (is_array($item)) {
                    $sort($item);
                }
            }
            unset($item);

            if (! array_is_list($items)) {
                ksort($items);
            }
        };

        $sort($value);

        return (string) json_encode($value);
    }

    private function display(string $module, string $field, mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item, $key): string => Str::headline((string) $key).': '.(is_scalar($item) ? (string) $item : json_encode($item)))
                ->implode(', ');
        }

        return $value === null || trim((string) $value) === '' ? 'Not set' : (string) $value;
    }

    private function refuse(string $message): ValidationException
    {
        return ValidationException::withMessages(['revert' => $message]);
    }
}
