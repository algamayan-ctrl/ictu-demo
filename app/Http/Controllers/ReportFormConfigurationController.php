<?php

namespace App\Http\Controllers;

use App\Enums\AccessClassification;
use App\Models\ReportFormTemplate;
use App\Services\AuditService;
use App\Services\OfficialEquipmentSummaryService;
use App\Support\OfficialReportTitle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportFormConfigurationController extends Controller
{
    private const REPORT_TYPES = [
        'SUMMARY_REQUESTS_EQUIPMENT' =>
            'Summary of Requests for Equipment',
    ];

    /**
     * Internal keys stay protected from the administrator.
     * The administrator changes only the official display labels,
     * inclusion and presentation order.
     */
    private const COLUMN_DEFINITIONS = [
        'control_no' => [
            'label' => 'Control No.',
            'order' => 1,
            'group' => null,
        ],

        'requesting_office' => [
            'label' => 'Requesting Office/Department',
            'order' => 2,
            'group' => null,
        ],

        'requested_equipment' => [
            'label' => 'Requested Equipment',
            'order' => 3,
            'group' => null,
        ],

        'dates_needed' => [
            'label' => 'Dates Needed',
            'order' => 4,
            'group' => null,
        ],

        'received_date' => [
            'label' => 'Date',
            'order' => 5,
            'group' => 'RECEIVED',
        ],

        'received_time' => [
            'label' => 'Time',
            'order' => 6,
            'group' => 'RECEIVED',
        ],

        'released_date' => [
            'label' => 'Date',
            'order' => 7,
            'group' => 'RELEASED',
        ],

        'released_time' => [
            'label' => 'Time',
            'order' => 8,
            'group' => 'RELEASED',
        ],

        'total_time_allocated' => [
            'label' => 'Total Time Allocated',
            'order' => 9,
            'group' => null,
        ],

        'remarks' => [
            'label' => 'Remarks',
            'order' => 10,
            'group' => null,
        ],
        // Optional fields are available in Add Column; they are not enabled
        // on existing institutional forms unless an administrator selects them.
        'borrower_name' => [
            'label' => 'Borrower',
            'order' => 11,
            'group' => null,
            'visible' => false,
        ],
        'requested_quantity' => [
            'label' => 'Total Quantity Requested',
            'order' => 12,
            'group' => null,
            'visible' => false,
        ],
        'approved_date' => [
            'label' => 'Final Approval Date',
            'order' => 13,
            'group' => null,
            'visible' => false,
        ],
        'purpose_event' => [
            'label' => 'Purpose / Event',
            'order' => 14,
            'group' => null,
            'visible' => false,
        ],
        'location' => [
            'label' => 'Venue / Location',
            'order' => 15,
            'group' => null,
            'visible' => false,
        ],
        'division' => [
            'label' => 'Division',
            'order' => 16,
            'group' => null,
            'visible' => false,
        ],
        'off_campus' => [
            'label' => 'Off-Campus',
            'order' => 17,
            'group' => null,
            'visible' => false,
        ],
        'approved_by' => [
            'label' => 'Approved By',
            'order' => 18,
            'group' => null,
            'visible' => false,
        ],
        'custody_no' => [
            'label' => 'Custody No.',
            'order' => 19,
            'group' => null,
            'visible' => false,
        ],
    ];

    /**
     * KPI meaning and computation are system-controlled.
     * Report Form Configuration may show/hide the performance summary
     * but may not redefine what the KPI means.
     */
    private const DEFAULT_FORMULA = [
        'enabled' => true,
        'label' => 'Formula',
        'numerator_label' =>
            'Number of requests responded on time',
        'denominator_label' =>
            'Total number of requests',
        'target_percent' => 100,
        'met_label' => 'MET',
        'not_met_label' => 'NOT MET',
    ];

    /**
     * Placeholders found only in revisions saved before the Report Title
     * and the reporting period were separated. New saves may not use them.
     */
    private const ALLOWED_TITLE_TOKENS =
        OfficialReportTitle::LEGACY_TOKENS;


    public function index(Request $request): View
    {
        $this->authorizeHead($request);

        $reportKey = strtoupper(
            (string) $request->query(
                'report',
                'SUMMARY_REQUESTS_EQUIPMENT'
            )
        );

        abort_unless(
            array_key_exists(
                $reportKey,
                self::REPORT_TYPES
            ),
            404
        );

        $templates = ReportFormTemplate::query()
            ->with('configuredBy')
            ->where('report_key', $reportKey)
            ->orderByDesc('version_no')
            ->get();

        $selectedId = (int) $request->query(
            'template',
            0
        );

        $selectedTemplate =
            $selectedId > 0
                ? $templates->firstWhere(
                    'id',
                    $selectedId
                )
                : null;

        /*
         * A working Draft is the first editing destination.
         * Otherwise the current Active revision is shown.
         */
        $selectedTemplate ??=
            $templates->firstWhere(
                'status',
                'DRAFT'
            );

        $selectedTemplate ??=
            $templates->firstWhere(
                'status',
                'ACTIVE'
            );

        $selectedTemplate ??=
            $templates->first();

        $draftTemplate =
            $templates->firstWhere(
                'status',
                'DRAFT'
            );

        $activeTemplate =
            $templates->firstWhere(
                'status',
                'ACTIVE'
            );

        return view(
            'administration.report-form-configuration',
            [
                'reportTypes' =>
                    self::REPORT_TYPES,

                'reportKey' =>
                    $reportKey,

                'templates' =>
                    $templates,

                'selectedTemplate' =>
                    $selectedTemplate,

                'draftTemplate' =>
                    $draftTemplate,

                'activeTemplate' =>
                    $activeTemplate,

                'activationChecklist' =>
                    $draftTemplate
                        ? $this->activationChecklist(
                            $draftTemplate
                        )
                        : [],

                'configuration' =>
                    $selectedTemplate
                        ? $selectedTemplate
                            ->configuration_json
                        : [],

                'columnDefinitions' =>
                    self::COLUMN_DEFINITIONS,

                'maxCustomColumns' =>
                    OfficialEquipmentSummaryService::MAX_CUSTOM_COLUMNS,

                'reportTitle' =>
                    OfficialReportTitle::baseTitle(
                        $selectedTemplate?->title_template
                    ),

                'legacyTitle' =>
                    OfficialReportTitle::isLegacy(
                        $selectedTemplate?->title_template
                    )
                        ? $selectedTemplate->title_template
                        : null,

                'titleSamples' => [
                    'month' => OfficialReportTitle::period(
                        now()->startOfMonth(),
                        now()->endOfMonth()
                    ),
                    'range' => OfficialReportTitle::period(
                        now()->startOfMonth(),
                        now()->startOfMonth()->addDays(14)
                    ),
                ],

                'systemFormula' =>
                    self::DEFAULT_FORMULA,
            ]
        );
    }


    /**
     * Save the working configuration.
     *
     * There is at most one Draft for a report type. Editing the Active
     * revision creates that Draft; later saves continue updating it
     * instead of producing Version 3, 4 and 5 for minor draft changes.
     */
    public function storeRevision(
        Request $request,
        AuditService $audit
    ): RedirectResponse {
        $this->authorizeHead($request);

        $data = $request->validate([
            'source_template_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'report_form_templates',
                    'id'
                ),
            ],

            'report_key' => [
                'required',
                Rule::in(
                    array_keys(
                        self::REPORT_TYPES
                    )
                ),
            ],

            'form_code' => [
                'nullable',
                'string',
                'max:80',
            ],

            'effectivity_date' => [
                'nullable',
                'date',
            ],

            'revision_label' => [
                'nullable',
                'string',
                'max:50',
            ],

            'header_republic' => [
                'nullable',
                'string',
                'max:150',
            ],

            'header_institution' => [
                'nullable',
                'string',
                'max:200',
            ],

            'header_location' => [
                'nullable',
                'string',
                'max:150',
            ],

            'page_size' => [
                'required',
                Rule::in([
                    'A4',
                    'LETTER',
                    'LEGAL',
                ]),
            ],

            'orientation' => [
                'required',
                Rule::in([
                    'LANDSCAPE',
                    'PORTRAIT',
                ]),
            ],

            'prepared_by_label' => [
                'required',
                'string',
                'max:80',
            ],

            'prepared_by_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'prepared_by_designation' => [
                'nullable',
                'string',
                'max:150',
            ],

            'noted_by_label' => [
                'required',
                'string',
                'max:80',
            ],

            'noted_by_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'noted_by_designation' => [
                'nullable',
                'string',
                'max:150',
            ],

            'column_labels' => [
                'required',
                'array',
            ],

            'column_labels.*' => [
                'required',
                'string',
                'max:120',
                // Headings are printed on the form: no line breaks or control characters.
                'not_regex:/[\p{Cc}]/u',
            ],

            'column_order' => [
                'required',
                'array',
            ],

            'column_order.*' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

        ]);

        // The official equipment form uses its system-defined title.
        // The selected report period is appended by the report renderer.
        // Ignore posted title overrides, including handcrafted requests.
        $data['title_template'] = OfficialReportTitle::DEFAULT_TITLE;

        $source = null;

        if (! empty($data['source_template_id'])) {
            $source = ReportFormTemplate::query()
                ->findOrFail(
                    $data['source_template_id']
                );

            abort_unless(
                $source->report_key
                    === $data['report_key'],
                422
            );
        }

        /*
         * Only the working Draft, or the Active revision when no Draft
         * exists yet, may be edited. Saving from any other revision would
         * silently overwrite the Draft in progress.
         */
        $existingDraft =
            ReportFormTemplate::query()
                ->where(
                    'report_key',
                    $data['report_key']
                )
                ->where(
                    'status',
                    'DRAFT'
                )
                ->first();

        if ($source?->status === 'ARCHIVED') {
            throw ValidationException::withMessages([
                'source_template_id' =>
                    'Archived revisions are read-only. Use "Use as New Revision" to start a Draft from it.',
            ]);
        }

        if (
            $existingDraft
            && $source
            && $source->id !== $existingDraft->id
        ) {
            throw ValidationException::withMessages([
                'source_template_id' =>
                    'A working Draft (Version '
                    .$existingDraft->version_no
                    .') already exists. Open that Draft to continue editing.',
            ]);
        }

        $columns = collect(
            self::COLUMN_DEFINITIONS
        )
            ->map(
                function (
                    array $definition,
                    string $key
                ) use (
                    $request,
                    $data
                ): array {
                    return [
                        'key' =>
                            $key,

                        'label' =>
                            $data[
                                'column_labels'
                            ][$key]
                            ?? $definition['label'],

                        'visible' =>
                            $request->boolean(
                                "column_visibility.$key"
                            ),

                        'order' =>
                            (int) (
                                $data[
                                    'column_order'
                                ][$key]
                                ?? $definition['order']
                            ),

                        'group' =>
                            $definition['group'],
                    ];
                }
            )
            ->values()
            ->concat($this->customColumns($request, $data))
            ->sortBy('order')
            ->values()
            ->all();

        if (! collect($columns)->firstWhere('key', 'control_no')['visible']) {
            throw ValidationException::withMessages([
                'column_visibility' => 'Control No. is required on official reports.',
            ]);
        }

        if (
            collect($columns)
                ->where('visible', true)
                ->isEmpty()
        ) {
            throw ValidationException::withMessages([
                'column_visibility' =>
                    'At least one report column must remain visible.',
            ]);
        }

        $sourceConfiguration =
            is_array(
                $source?->configuration_json
            )
                ? $source->configuration_json
                : [];

        // The SPMU Head may revise the identifier in a Draft. The Active
        // revision keeps its approved code until explicit activation.
        // Letterhead remains institution-controlled and cannot be posted.
        $data['form_code'] = trim((string) ($data['form_code'] ?? ''));
        if ($data['form_code'] !== ''
            && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $data['form_code'])) {
            throw ValidationException::withMessages([
                'form_code' => 'Use letters, numbers, hyphens, periods, underscores or slashes only.',
            ]);
        }
        $data['form_code'] = $data['form_code'] ?: null;
        $fixedHeader = [
            'show_logo' => true,
            'republic' => 'Republic of the Philippines',
            'institution' => 'CAMARINES SUR POLYTECHNIC COLLEGES',
            'location' => 'Nabua, Camarines Sur',
        ];

        foreach (['RECEIVED' => ['received_date', 'received_time'],
                   'RELEASED' => ['released_date', 'released_time']] as $group => $keys) {
            $visible = collect($columns)
                ->whereIn('key', $keys)
                ->pluck('visible')
                ->unique()
                ->all();
            if (count($visible) > 1) {
                throw ValidationException::withMessages([
                    'column_visibility' => $group.' Date and Time must be included or removed together.',
                ]);
            }
        }

        $formula =
            is_array(
                $sourceConfiguration['formula']
                ?? null
            )
                ? $sourceConfiguration['formula']
                : self::DEFAULT_FORMULA;

        /*
         * Only visibility is editable here.
         * The actual KPI definition remains protected.
         */
        $formula = array_merge(
            self::DEFAULT_FORMULA,
            $formula,
            [
                'enabled' =>
                    $request->boolean(
                        'formula_enabled'
                    ),
            ]
        );

        $configuration = [
            'header' => $fixedHeader,

            'columns' =>
                $columns,

            'formula' =>
                $formula,

            'signatories' => [
                'prepared_by_label' =>
                    $data['prepared_by_label'],

                'prepared_by_name' =>
                    $data['prepared_by_name']
                    ?? '',

                'prepared_by_designation' =>
                    $data[
                        'prepared_by_designation'
                    ]
                    ?? '',

                'noted_by_label' =>
                    $data['noted_by_label'],

                'noted_by_name' =>
                    $data['noted_by_name']
                    ?? '',

                'noted_by_designation' =>
                    $data[
                        'noted_by_designation'
                    ]
                    ?? '',

                'show_date' =>
                    $request->boolean(
                        'show_signature_date'
                    ),
            ],

            'page' => [
                'size' =>
                    $data['page_size'],

                'orientation' =>
                    $data['orientation'],
            ],
        ];

        // No manual reason field: describe the actual changed sections
        // automatically so revision and audit history remain meaningful.
        $changed = [];
        if (($source?->form_code ?? null) !== $data['form_code']) {
            $changed[] = 'form code';
        }
        if (($source?->revision_label ?? null) !== ($data['revision_label'] ?? null)) {
            $changed[] = 'revision label';
        }
        if (($source?->effectivity_date?->format('Y-m-d') ?? null) !== ($data['effectivity_date'] ?? null)) {
            $changed[] = 'effectivity date';
        }
        foreach (['columns' => 'table columns', 'signatories' => 'signatories',
                  'page' => 'page setup', 'formula' => 'performance summary'] as $key => $label) {
            if (($sourceConfiguration[$key] ?? []) != ($configuration[$key] ?? [])) {
                $changed[] = $label;
            }
        }
        $data['change_reason'] = $changed
            ? 'Updated '.implode(', ', $changed).'.'
            : 'Saved report form configuration.';

        $created = false;

        $template = DB::transaction(
            function () use (
                $request,
                $data,
                $configuration,
                &$created
            ): ReportFormTemplate {
                $draft =
                    ReportFormTemplate::query()
                        ->where(
                            'report_key',
                            $data['report_key']
                        )
                        ->where(
                            'status',
                            'DRAFT'
                        )
                        ->lockForUpdate()
                        ->first();

                if ($draft) {
                    $draft->update([
                        'form_code' =>
                            $data['form_code']
                            ?? null,

                        'effectivity_date' =>
                            $data['effectivity_date']
                            ?? null,

                        'revision_label' =>
                            $data['revision_label']
                            ?? null,

                        'title_template' =>
                            $data[
                                'title_template'
                            ],

                        'configuration_json' =>
                            $configuration,

                        'change_reason' =>
                            $data[
                                'change_reason'
                            ],

                        'configured_by_user_id' =>
                            $request->user()->id,
                    ]);

                    return $draft->fresh();
                }

                $latest =
                    ReportFormTemplate::query()
                        ->where(
                            'report_key',
                            $data['report_key']
                        )
                        ->lockForUpdate()
                        ->orderByDesc(
                            'version_no'
                        )
                        ->first();

                $created = true;

                return ReportFormTemplate::query()
                    ->create([
                        'report_key' =>
                            $data['report_key'],

                        'version_no' =>
                            ($latest?->version_no ?? 0)
                            + 1,

                        'status' =>
                            'DRAFT',

                        'form_code' =>
                            $data['form_code']
                            ?? null,

                        'effectivity_date' =>
                            $data['effectivity_date']
                            ?? null,

                        'revision_label' =>
                            $data['revision_label']
                            ?? null,

                        'title_template' =>
                            $data[
                                'title_template'
                            ],

                        'configuration_json' =>
                            $configuration,

                        'change_reason' =>
                            $data[
                                'change_reason'
                            ],

                        'configured_by_user_id' =>
                            $request->user()->id,

                        'activated_at' =>
                            null,
                    ]);
            }
        );

        $audit->record(
            $created
                ? 'REPORT_FORM_REVISION_CREATED'
                : 'REPORT_FORM_DRAFT_UPDATED',
            $template,
            reason: $data['change_reason'],
            after: [
                'report_key' =>
                    $template->report_key,

                'version_no' =>
                    $template->version_no,

                'revision_label' =>
                    $template->revision_label,

                'status' =>
                    $template->status,
            ]
        );

        return redirect()
            ->route(
                'policies.report-forms.index',
                [
                    'report' =>
                        $template->report_key,

                    'template' =>
                        $template->id,
                ]
            )
            ->with(
                'status',
                $created
                    ? 'Working Draft created. Review the preview before activation.'
                    : 'Working Draft saved.'
            );
    }


    /**
     * Copy an older immutable revision into a new working Draft.
     */
    public function duplicate(
        Request $request,
        ReportFormTemplate $template,
        AuditService $audit
    ): RedirectResponse {
        $this->authorizeHead($request);

        if ($template->status === 'DRAFT') {
            return redirect()
                ->route(
                    'policies.report-forms.index',
                    [
                        'report' =>
                            $template->report_key,

                        'template' =>
                            $template->id,
                    ]
                );
        }

        $existingDraft =
            ReportFormTemplate::query()
                ->where(
                    'report_key',
                    $template->report_key
                )
                ->where(
                    'status',
                    'DRAFT'
                )
                ->first();

        if ($existingDraft) {
            return redirect()
                ->route(
                    'policies.report-forms.index',
                    [
                        'report' =>
                            $existingDraft
                                ->report_key,

                        'template' =>
                            $existingDraft->id,
                    ]
                )
                ->with(
                    'status',
                    'A working Draft already exists. Continue editing that Draft before creating another revision.'
                );
        }

        $draft = DB::transaction(
            function () use (
                $request,
                $template
            ): ReportFormTemplate {
                $latest =
                    ReportFormTemplate::query()
                        ->where(
                            'report_key',
                            $template->report_key
                        )
                        ->lockForUpdate()
                        ->orderByDesc(
                            'version_no'
                        )
                        ->first();

                return ReportFormTemplate::query()
                    ->create([
                        'report_key' =>
                            $template->report_key,

                        'version_no' =>
                            ($latest?->version_no ?? 0)
                            + 1,

                        'status' =>
                            'DRAFT',

                        'form_code' =>
                            $template->form_code,

                        'effectivity_date' =>
                            $template->effectivity_date,

                        'revision_label' =>
                            $template->revision_label,

                        'title_template' =>
                            $template->title_template,

                        'configuration_json' =>
                            $template->configuration_json,

                        'change_reason' =>
                            'Working Draft created from Version '
                            .$template->version_no.'.',

                        'configured_by_user_id' =>
                            $request->user()->id,

                        'activated_at' =>
                            null,
                    ]);
            }
        );

        $audit->record(
            'REPORT_FORM_REVISION_COPIED',
            $draft,
            reason:
                'Created a new working Draft from Version '
                .$template->version_no.'.',
            after: [
                'source_template_id' =>
                    $template->id,

                'source_version_no' =>
                    $template->version_no,

                'new_version_no' =>
                    $draft->version_no,
            ]
        );

        return redirect()
            ->route(
                'policies.report-forms.index',
                [
                    'report' =>
                        $draft->report_key,

                    'template' =>
                        $draft->id,
                ]
            )
            ->with(
                'status',
                'Working Draft created from the selected historical revision.'
            );
    }


    /**
     * Only a complete Draft may become the official Active revision.
     */
    public function activate(
        Request $request,
        ReportFormTemplate $template,
        AuditService $audit
    ): RedirectResponse {
        $this->authorizeHead($request);

        abort_unless(
            $template->status === 'DRAFT',
            422,
            'Only a working Draft may be activated.'
        );

        $this->assertReadyForActivation(
            $template
        );

        $previousActive = null;

        DB::transaction(
            function () use (
                $request,
                $template,
                &$previousActive
            ): void {
                $previousActive =
                    ReportFormTemplate::query()
                        ->where(
                            'report_key',
                            $template->report_key
                        )
                        ->where(
                            'status',
                            'ACTIVE'
                        )
                        ->lockForUpdate()
                        ->first();

                ReportFormTemplate::query()
                    ->where(
                        'report_key',
                        $template->report_key
                    )
                    ->where(
                        'status',
                        'ACTIVE'
                    )
                    ->where(
                        'id',
                        '<>',
                        $template->id
                    )
                    ->update([
                        'status' =>
                            'ARCHIVED',

                        'updated_at' =>
                            now(),
                    ]);

                $template->update([
                    'status' =>
                        'ACTIVE',

                    'configured_by_user_id' =>
                        $request->user()->id,

                    'activated_at' =>
                        now(),
                ]);
            }
        );

        $audit->record(
            'REPORT_FORM_REVISION_ACTIVATED',
            $template,
            reason:
                'SPMU Admin/Head activated the reviewed report form revision.',
            before: [
                'active_template_id' =>
                    $previousActive?->id,

                'active_version_no' =>
                    $previousActive?->version_no,
            ],
            after: [
                'active_template_id' =>
                    $template->id,

                'active_version_no' =>
                    $template->version_no,
            ]
        );

        return redirect()
            ->route(
                'policies.report-forms.index',
                [
                    'report' =>
                        $template->report_key,

                    'template' =>
                        $template->id,
                ]
            )
            ->with(
                'status',
                'Report form revision activated. The previous Active revision was archived.'
            );
    }


    /**
     * Remove an unactivated working Draft. Active and Archived revisions
     * are approved history and can never be deleted.
     */
    public function discard(
        Request $request,
        ReportFormTemplate $template,
        AuditService $audit
    ): RedirectResponse {
        $this->authorizeHead($request);

        abort_unless(
            $template->status === 'DRAFT',
            422,
            'Only a working Draft may be discarded.'
        );

        $snapshot = [
            'report_key' =>
                $template->report_key,

            'version_no' =>
                $template->version_no,

            'revision_label' =>
                $template->revision_label,

            'change_reason' =>
                $template->change_reason,
        ];

        $templateId = $template->id;
        $reportKey = $template->report_key;

        $template->delete();

        $audit->record(
            'REPORT_FORM_DRAFT_DISCARDED',
            ReportFormTemplate::class,
            $templateId,
            reason:
                'SPMU Admin/Head discarded the working Draft (Version '
                .$snapshot['version_no'].').',
            before: $snapshot
        );

        return redirect()
            ->route(
                'policies.report-forms.index',
                [
                    'report' =>
                        $reportKey,
                ]
            )
            ->with(
                'status',
                'Working Draft discarded. The Active revision was not changed.'
            );
    }


    /**
     * Custom columns posted by the editor.
     *
     * A custom column is identified by its generated key, never by its
     * heading, and carries no data mapping: the report leaves its cells blank.
     * Any posted key that is neither a system field nor a well-formed custom
     * key is refused rather than stored.
     *
     * @return list<array{key: string, label: string, visible: bool, order: int, group: null, custom: bool}>
     */
    private function customColumns(
        Request $request,
        array $data
    ): array {
        $keys = collect(array_keys($data['column_labels']))
            ->map(fn ($key): string => (string) $key)
            ->reject(fn (string $key): bool => array_key_exists($key, self::COLUMN_DEFINITIONS))
            ->values();

        if ($keys->contains(fn (string $key): bool => ! OfficialEquipmentSummaryService::isCustomKey($key))) {
            throw ValidationException::withMessages([
                'column_labels' => 'A column has an unsupported identifier. Reload the page and add the column again.',
            ]);
        }

        // A custom column the editor marks as not included has been removed.
        $keys = $keys
            ->filter(fn (string $key): bool => $request->boolean("column_visibility.$key", true))
            ->values();

        if ($keys->count() > OfficialEquipmentSummaryService::MAX_CUSTOM_COLUMNS) {
            throw ValidationException::withMessages([
                'column_labels' => 'A form may have at most '
                    .OfficialEquipmentSummaryService::MAX_CUSTOM_COLUMNS.' custom columns.',
            ]);
        }

        return $keys
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => $data['column_labels'][$key],
                'visible' => true,
                'order' => (int) ($data['column_order'][$key] ?? 100),
                'group' => null,
                'custom' => true,
            ])
            ->all();
    }


    /**
     * Activation requirements, listed for the Draft review panel.
     *
     * @return list<array{key: string, label: string, ok: bool}>
     */
    private function activationChecklist(
        ReportFormTemplate $template
    ): array {
        $configuration =
            is_array(
                $template->configuration_json
            )
                ? $template->configuration_json
                : [];

        $signatories =
            is_array(
                $configuration['signatories']
                ?? null
            )
                ? $configuration['signatories']
                : [];

        $columns = collect(
            $configuration['columns']
            ?? []
        );

        $titleTokensValid =
            filled($template->title_template)
            && $this->invalidTitleTokens(
                $template->title_template
            ) === [];

        $signatoriesComplete =
            collect([
                'prepared_by_name',
                'prepared_by_designation',
                'noted_by_name',
                'noted_by_designation',
            ])->every(
                fn (string $key): bool =>
                    filled($signatories[$key] ?? null)
            );

        return [
            [
                'key' => 'form_code',
                'label' => 'Form Code is set',
                'ok' => filled($template->form_code),
            ],
            [
                'key' => 'revision_label',
                'label' => 'Form Revision is set',
                'ok' => filled($template->revision_label),
            ],
            [
                'key' => 'effectivity_date',
                'label' => 'Effectivity Date is set',
                'ok' => (bool) $template->effectivity_date,
            ],
            [
                'key' => 'title_template',
                'label' => 'Report Title is set',
                'ok' => $titleTokensValid,
            ],
            [
                'key' => 'columns',
                'label' => 'At least one table column is included',
                'ok' => $columns
                    ->where('visible', true)
                    ->isNotEmpty(),
            ],
            [
                'key' => 'signatories',
                'label' => 'Both signatories have a printed name and designation',
                'ok' => $signatoriesComplete,
            ],
        ];
    }


    private function assertReadyForActivation(
        ReportFormTemplate $template
    ): void {
        $errors = collect(
            $this->activationChecklist(
                $template
            )
        )
            ->reject(
                fn (array $item): bool =>
                    $item['ok']
            )
            ->mapWithKeys(
                fn (array $item): array => [
                    $item['key'] =>
                        'Cannot activate yet: '
                        .lcfirst($item['label'])
                        .' is required.',
                ]
            )
            ->all();

        if ($errors !== []) {
            throw ValidationException::withMessages(
                $errors
            );
        }
    }


    /**
     * @return list<string>
     */
    private function invalidTitleTokens(
        string $title
    ): array {
        preg_match_all(
            '/\{[^}]+\}/',
            $title,
            $matches
        );

        return array_values(
            array_diff(
                array_unique(
                    $matches[0] ?? []
                ),
                self::ALLOWED_TITLE_TOKENS
            )
        );
    }


    /**
     * The Report Title is stored without any reporting period. The period
     * is derived from the dates chosen when the official report is generated.
     */
    private function assertPlainReportTitle(
        string $title
    ): string {
        $title = trim(
            (string) preg_replace(
                '/\s+/',
                ' ',
                $title
            )
        );

        if (preg_match('/[{}]/', $title)) {
            throw ValidationException::withMessages([
                'title_template' =>
                    'Remove the placeholder from the Report Title. The reporting period is added automatically from the dates chosen in Generate Official Report.',
            ]);
        }

        if (preg_match('/\bfor\s+the\s+(?:month|period|year)\b/i', $title)) {
            throw ValidationException::withMessages([
                'title_template' =>
                    'Enter only the Report Title (e.g. "'
                    .OfficialReportTitle::DEFAULT_TITLE
                    .'"). The "for the Month of …" wording is added automatically.',
            ]);
        }

        return $title;
    }


    private function authorizeHead(
        Request $request
    ): void {
        abort_unless(
            strtoupper(
                (string) $request
                    ->session()
                    ->get(
                        'active_workspace'
                    )
            ) === 'SPMU'
            && $request
                ->user()
                ?->access_classification
                === AccessClassification::SpmuHead,
            403,
            'Only the SPMU Admin/Head may manage report form configuration.'
        );
    }
}