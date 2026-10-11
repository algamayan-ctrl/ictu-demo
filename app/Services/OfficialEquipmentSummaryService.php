<?php

namespace App\Services;

use App\Enums\ApprovalStage;
use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;
use App\Models\ReportFormTemplate;
use App\Reports\Builders\BorrowingActivityReport;
use App\Reports\OperationalStatus;
use App\Support\OfficialReportTitle;
use App\Support\OrganizationalStructure;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Official CSPC-F-SPMU-22: one row per submitted borrowing request. */
class OfficialEquipmentSummaryService
{
    public const KEY = 'SUMMARY_REQUESTS_EQUIPMENT';

    /**
     * The ten columns of the official CSPC form. Only these are shown when a
     * stored revision says nothing about a column.
     */
    public const OFFICIAL_KEYS = [
        'control_no', 'requesting_office', 'requested_equipment', 'dates_needed',
        'received_date', 'received_time', 'released_date', 'released_time',
        'total_time_allocated', 'remarks',
    ];

    /**
     * Optional system fields. A revision shows one only when its own stored
     * configuration includes it, so adding a field here never changes an
     * Active or Archived revision: it must be included in a Draft and that
     * Draft activated.
     */
    public const OPTIONAL_KEYS = [
        'borrower_name', 'requested_quantity', 'approved_date',
        'purpose_event', 'location', 'division', 'off_campus',
        'approved_by', 'custody_no',
    ];

    public const KEYS = [...self::OFFICIAL_KEYS, ...self::OPTIONAL_KEYS];

    /** Headings used when a stored revision carries no label for a column. */
    public const LABELS = [
        'control_no' => 'Control No.', 'requesting_office' => 'Requesting Office/Department',
        'requested_equipment' => 'Requested Equipment', 'dates_needed' => 'Dates Needed',
        'received_date' => 'Date', 'received_time' => 'Time',
        'released_date' => 'Date', 'released_time' => 'Time',
        'total_time_allocated' => 'Total Time Allocated', 'remarks' => 'Remarks',
        'borrower_name' => 'Borrower', 'requested_quantity' => 'Total Quantity Requested',
        'approved_date' => 'Final Approval Date',
        'purpose_event' => 'Purpose / Event', 'location' => 'Venue / Location',
        'division' => 'Division', 'off_campus' => 'Off-Campus',
        'approved_by' => 'Approved By', 'custody_no' => 'Custody No.',
    ];

    /** Relative column widths shared by the sheet, PDF, Word and Excel layouts. */
    private const WIDTHS = [
        'control_no' => 11, 'requesting_office' => 14, 'requested_equipment' => 18,
        'dates_needed' => 11, 'received_date' => 8, 'received_time' => 7,
        'released_date' => 8, 'released_time' => 7, 'total_time_allocated' => 8,
        'remarks' => 10, 'borrower_name' => 12,
        'requested_quantity' => 8, 'approved_date' => 10,
        'purpose_event' => 14, 'location' => 12, 'division' => 10,
        'off_campus' => 6, 'approved_by' => 12, 'custody_no' => 11,
    ];

    private const DEFAULT_WIDTH = 10;

    /**
     * A custom column is a heading the SPMU Head adds to a revision. It has no
     * system data source, so its cells are always blank. Its key is generated
     * once and never derived from the heading: renaming keeps the same key.
     * The prefix keeps custom keys apart from system fields and from the
     * underscore-prefixed presentation keys on a row.
     */
    public const CUSTOM_KEY_PATTERN = '/^custom_[a-z0-9]{6,24}$/';

    public const MAX_CUSTOM_COLUMNS = 30;

    public static function isCustomKey(mixed $key): bool
    {
        return is_string($key) && preg_match(self::CUSTOM_KEY_PATTERN, $key) === 1;
    }

    public function build(Carbon $from, Carbon $to): array
    {
        $template = ReportFormTemplate::query()
            ->where('report_key', self::KEY)
            ->where('status', 'ACTIVE')
            ->orderByDesc('version_no')
            ->firstOrFail();

        $configuration = is_array($template->configuration_json) ? $template->configuration_json : [];
        $stored = collect($configuration['columns'] ?? [])->keyBy('key');
        $columns = collect(self::KEYS)->map(function (string $key, int $index) use ($stored): array {
            $col = $stored->get($key) ?? [];
            return [
                'key' => $key,
                'label' => trim((string) ($col['label'] ?? self::LABELS[$key])),
                'visible' => (bool) ($col['visible'] ?? in_array($key, self::OFFICIAL_KEYS, true)),
                'order' => (int) ($col['order'] ?? ($index + 1)),
                'group' => match ($key) {
                    'received_date', 'received_time' => 'RECEIVED',
                    'released_date', 'released_time' => 'RELEASED',
                    default => null,
                },
                'custom' => false,
            ];
        });

        // Custom columns exist only in the revision that stores them, so they
        // reach the official report only once that revision is the Active one.
        $custom = collect($configuration['columns'] ?? [])
            ->filter(fn ($col): bool => is_array($col)
                && ($col['custom'] ?? false) === true
                && self::isCustomKey($col['key'] ?? null))
            ->unique('key')
            ->take(self::MAX_CUSTOM_COLUMNS)
            ->map(fn (array $col): array => [
                'key' => $col['key'],
                'label' => trim((string) ($col['label'] ?? '')) ?: 'Custom Column',
                'visible' => (bool) ($col['visible'] ?? true),
                'order' => (int) ($col['order'] ?? 100),
                'group' => null,
                'custom' => true,
            ])
            ->values();

        $columns = $columns->concat($custom)
            ->filter(fn (array $col): bool => $col['visible'])->sortBy('order')->values();
        $blankCustomCells = array_fill_keys($columns->where('custom', true)->pluck('key')->all(), '');
        $weight = max(1, $columns->sum(fn (array $col): int => self::WIDTHS[$col['key']] ?? self::DEFAULT_WIDTH));
        $columns = $columns->map(fn (array $col): array => $col + [
            'width' => round((self::WIDTHS[$col['key']] ?? self::DEFAULT_WIDTH) * 100 / $weight, 2),
        ])->all();

        // A request's submission timestamp is its receipt. For older rows where
        // the timestamp was not stored, the request creation date is a stated fallback.
        $requests = BorrowingRequest::query()
            ->with(['currentVersion.items.inventoryItem', 'currentVersion.approvalSteps.approver', 'accountableUnit', 'custody', 'borrower'])
            ->whereNotIn('status', ['DRAFT', 'SIGNED'])
            ->where(function ($query) use ($from, $to): void {
                $query->whereHas('currentVersion', fn ($version) => $version
                    ->whereBetween('submitted_at', [$from, $to]))
                    ->orWhere(function ($legacy) use ($from, $to): void {
                        $legacy->whereBetween('created_at', [$from, $to])
                            ->whereHas('currentVersion', fn ($version) => $version->whereNull('submitted_at'));
                    });
            })
            ->orderBy('id')
            ->get();

        $rows = $requests->map(function (BorrowingRequest $request) use ($blankCustomCells): array {
            $version = $request->currentVersion;
            $received = $version?->submitted_at ?: $request->created_at;
            $released = $request->custody?->released_at;
            $received = $received ? Carbon::parse($received) : null;
            $released = $released ? Carbon::parse($released) : null;
            $neededFrom = $version?->schedule_date ?: $version?->needed_from;
            $neededUntil = $version?->return_date ?: $version?->return_due_at;
            $needed = $neededFrom ? Carbon::parse($neededFrom)->format('d M Y') : '—';
            if ($neededUntil && (! $neededFrom || ! Carbon::parse($neededFrom)->isSameDay($neededUntil))) {
                $needed .= ' – '.Carbon::parse($neededUntil)->format('d M Y');
            }

            $equipment = $version?->items?->map(function ($item): string {
                $description = trim((string) ($item->description_snapshot ?: $item->inventoryItem?->unique_description ?: 'Unspecified equipment'));
                return $description.' ('.(int) $item->requested_quantity.')';
            })->filter()->implode('; ') ?: '—';

            $minutes = ($received && $released)
                ? max(0, (int) floor(($released->timestamp - $received->timestamp) / 60))
                : null;
            $allocated = $minutes === null ? '—' : sprintf('%d h %d min', intdiv($minutes, 60), $minutes % 60);
            [$statusCode, $remarks] = $this->remarks($request);

            // The SPMU step that actually carried the approval. A delegated
            // approval is recorded against the delegate who signed it.
            $approvedStep = $version?->approvalSteps
                ?->filter(fn ($step): bool => $step->stage_code === ApprovalStage::Spmu
                    && (string) $step->decision === 'APPROVED')
                ->sortByDesc('sequence_no')
                ->first();
            $text = static fn (mixed $value): string => trim((string) $value) !== '' ? trim((string) $value) : '—';

            // Custom columns have no data source: every cell is left blank for
            // handwritten entry. System values below can never be overridden.
            return [
                'control_no' => (string) $request->request_no,
                'requesting_office' => (string) ($request->accountableUnit?->unit_name ?: $version?->office_unit ?: '—'),
                'requested_equipment' => $equipment,
                'dates_needed' => $needed,
                'received_date' => $received?->format('d M Y') ?? '—',
                'received_time' => $received?->format('g:i A') ?? '—',
                'released_date' => $released?->format('d M Y') ?? '—',
                'released_time' => $released?->format('g:i A') ?? '—',
                'total_time_allocated' => $allocated,
                'remarks' => $remarks,
                'borrower_name' => (string) ($request->borrower?->full_name
                    ?: $request->borrower?->name ?: '—'),
                'requested_quantity' => (string) ($version?->items?->sum('requested_quantity') ?? 0),
                'approved_date' => $request->final_approved_at?->format('d M Y') ?? '—',
                'purpose_event' => $text($version?->purpose_event),
                'location' => $text($version?->location),
                'division' => filled($version?->division_code)
                    ? OrganizationalStructure::label($version->division_code)
                    : '—',
                'off_campus' => $version === null ? '—' : ($version->off_campus ? 'Yes' : 'No'),
                'approved_by' => $text($approvedStep?->approver?->full_name),
                'custody_no' => $text($request->custody?->custody_no),

                // Presentation only (system preview drilldown and badge); every
                // official format reads the configured column keys and skips these.
                '_request_id' => (int) $request->id,
                '_link' => route('requests.show', $request),
                '_released' => $released !== null,
                '_tone_remarks' => BorrowingActivityReport::statusTone($statusCode),
            ] + $blankCustomCells;
        })->values();

        // The reporting period comes from the selected dates, never from the
        // form's effectivity date. Legacy placeholder titles are read, not rewritten.
        $period = OfficialReportTitle::period($from, $to);
        $reportTitle = OfficialReportTitle::baseTitle($template->title_template);

        return [
            'template' => $template,
            'configuration' => $configuration,
            'columns' => $columns,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'title' => $reportTitle.' '.$period['phrase'],
            'report_title' => $reportTitle,
            'period' => $period['text'],
            'period_label' => $period['label'],
            'period_kind' => $period['kind'],
            'coverage' => $period['coverage'],
            'total' => $rows->count(),
            'released_count' => $rows->where('_released', true)->count(),
            // No institutional turnaround-time threshold is present in this source.
            // An unsubstantiated numerator or 100% (MET) must not be published.
            'on_time_count' => null,
            'on_time_percent' => null,
            'generated_at' => now(),
        ];
    }

    /**
     * Remarks reports where the request stands operationally when the report
     * is generated: review and decision states come from the request, and once
     * a custody transaction exists it is authoritative (Ready for Release,
     * Pickup Scheduled, Released / On Custody, Return Processing, Overdue,
     * Completed ...), the same rule the other reports use.
     *
     * This is the current status, not the status at the end of the selected
     * period: regenerating an earlier period later can show a different
     * Remarks value for the same request (for example "Released / On Custody"
     * becoming "Completed"). Nothing is stored or changed here.
     *
     * @return array{0: string, 1: string} [status code, display label]
     */
    private function remarks(BorrowingRequest $request): array
    {
        $status = $request->status;

        if (! $status instanceof RequestStatus) {
            $code = (string) $status;

            return [$code, OperationalStatus::label($code)];
        }

        // A cancelled, expired or rejected request stays that way. Cancellation
        // also stamps closed_at on any custody record, which must not make the
        // request read as Completed.
        if (! $status->isOpen()) {
            return [$status->value, $status->label()];
        }

        [$code, $label] = OperationalStatus::forRequest($request);

        return [$code, OperationalStatus::label($code, $label)];
    }
}
