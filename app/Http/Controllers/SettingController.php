<?php

namespace App\Http\Controllers;

use App\Enums\AccessClassification;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingController extends Controller
{
    private const MAX_UPLOAD_MB = 64;

    public const ICTU_SETTING_KEYS = [
        'max_upload_mb',
        'backup_schedule',
    ];

    public const PROTECTED_DIRECT_SETTING_KEYS = [
        'overdue_grace_hours',
        'due_soon_hours',
        'billing_statement_template_version',
        'laundry_form_template_version',
        'gate_pass_template_version',
        /* Internal one-time bootstrap marker (spmu:bootstrap); never a setting to edit. */
        'reference_data_bootstrap',
    ];

    public function index(Request $request): View
    {
        $this->authorizeConfiguration($request);

        $classification = $request->user()?->access_classification;
        $isIctu = $classification === AccessClassification::IctuMaintainer;

        if ($isIctu) {
            /*
             * ICTU owns only technical configuration. Operational policy,
             * fees, and workflow deadlines are intentionally excluded from
             * this workspace.
             */
            $settings = SystemSetting::query()
                ->whereIn('setting_key', self::ICTU_SETTING_KEYS)
                ->orderBy('group_code')
                ->orderBy('setting_key')
                ->get();
        } else {
            /*
             * SPMU Admin/Head owns business/operational configuration.
             * ICTU-owned technical settings and legacy compatibility rows are
             * not exposed here.
             */
            $hiddenSettingKeys = array_values(array_unique(array_merge(
                self::PROTECTED_DIRECT_SETTING_KEYS,
                self::ICTU_SETTING_KEYS,
            )));

            $settings = SystemSetting::query()
                ->whereNotIn('setting_key', $hiddenSettingKeys)
                ->orderBy('group_code')
                ->orderBy('setting_key')
                ->get();
        }

        return view('administration.settings', compact('settings', 'isIctu'));
    }

    public function update(
        Request $request,
        SystemSetting $setting,
        AuditService $audit
    ): RedirectResponse {
        $this->authorizeConfiguration($request);
        $this->authorizeSettingOwnership($request, $setting);

        if ($setting->setting_key === 'backup_schedule') {
            $data = $request->validate([
                'backup_time' => ['required', 'date_format:H:i'],
                'backup_recurrence' => ['required', 'in:daily,weekly,monthly'],
                'reason' => ['nullable', 'string', 'max:1000'],
            ]);

            $after = [
                'time' => $data['backup_time'],
                'recurrence' => $data['backup_recurrence'],
            ];
        } elseif ($setting->setting_key === 'max_upload_mb') {
            $data = $request->validate([
                'value' => ['required', 'integer', 'min:1', 'max:'.self::MAX_UPLOAD_MB],
                'reason' => ['nullable', 'string', 'max:1000'],
            ]);

            $after = (int) $data['value'];
        } else {
            $data = $request->validate([
                'value' => ['nullable', 'string', 'max:2000'],
                'reason' => ['nullable', 'string', 'max:1000'],
            ]);

            $after = match ($setting->data_type) {
                'INTEGER' => filled($data['value']) ? (int) $data['value'] : null,
                'MONEY' => filled($data['value']) ? round((float) $data['value'], 2) : null,
                default => filled($data['value']) ? $data['value'] : null,
            };
        }

        /*
         * The change log column is not nullable, so an omitted reason is
         * recorded explicitly rather than stored as an empty string.
         */
        $data['reason'] = filled($data['reason'] ?? null)
            ? trim($data['reason'])
            : 'No reason provided.';

        $before = $setting->value_json;

        DB::transaction(function () use (
            $setting,
            $request,
            $before,
            $after,
            $data,
            $audit
        ): void {
            $setting->update([
                'value_json' => $after,
                'updated_by_user_id' => $request->user()->id,
            ]);

            DB::table('configuration_changes')->insert([
                'system_setting_id' => $setting->id,
                'changed_by_user_id' => $request->user()->id,
                'before_value_json' => json_encode($before),
                'after_value_json' => json_encode($after),
                'reason' => $data['reason'],
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $audit->record(
                'SYSTEM_SETTING_CHANGED',
                $setting,
                reason: $data['reason'],
                before: ['value' => $before],
                after: ['value' => $after]
            );
        });

        return back()->with(
            'status',
            $request->user()?->access_classification === AccessClassification::IctuMaintainer
                ? 'System configuration updated with before/after audit history.'
                : 'Operational configuration updated with before/after audit history.'
        );
    }

    private function authorizeSettingOwnership(
        Request $request,
        SystemSetting $setting
    ): void {
        $classification = $request->user()?->access_classification;
        $key = (string) $setting->setting_key;

        abort_if(
            in_array($key, self::PROTECTED_DIRECT_SETTING_KEYS, true),
            403,
            'This compatibility or template-version value is not directly editable.'
        );

        if ($classification === AccessClassification::IctuMaintainer) {
            abort_unless(
                in_array($key, self::ICTU_SETTING_KEYS, true),
                403,
                'ICTU may change only technical system configuration.'
            );

            return;
        }

        abort_unless(
            $classification === AccessClassification::SpmuHead
                && ! in_array($key, self::ICTU_SETTING_KEYS, true),
            403,
            'This setting is not owned by SPMU Admin/Head.'
        );
    }

    private function authorizeConfiguration(Request $request): void
    {
        abort_unless(
            in_array(
                $request->user()?->access_classification,
                [
                    AccessClassification::SpmuHead,
                    AccessClassification::IctuMaintainer,
                ],
                true
            ),
            403,
            'Only SPMU Admin/Head or ICTU Maintainer may access configuration.'
        );
    }
}
