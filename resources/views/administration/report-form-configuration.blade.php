@extends('layouts.app', ['title' => 'Report Form Configuration'])

@section('content')

@php
    $config = $configuration ?? [];

    $header = $config['header'] ?? [];
    $formula = array_merge(
        $systemFormula ?? [],
        $config['formula'] ?? []
    );
    $signatories = $config['signatories'] ?? [];
    $page = $config['page'] ?? [];

    $storedColumns = collect($config['columns'] ?? [])->keyBy('key');

    $editorColumns = collect($columnDefinitions)
        ->map(function ($definition, $key) use ($storedColumns) {
            $stored = $storedColumns->get($key, []);

            return [
                'key' => $key,
                'label' => $stored['label'] ?? $definition['label'],
                'visible' => $stored['visible'] ?? ($definition['visible'] ?? true),
                'order' => $stored['order'] ?? $definition['order'],
                'group' => $definition['group'],
                'custom' => false,
            ];
        })
        ->values();

    /*
     * Custom columns belong to the revision that stores them. After a failed
     * save the posted set is shown instead, so columns added or removed in
     * that attempt are not lost.
     */
    $isCustomKey = fn ($key) => \App\Services\OfficialEquipmentSummaryService::isCustomKey($key);
    $postedLabels = old('column_labels');

    $customColumns = is_array($postedLabels)
        ? collect($postedLabels)
            ->filter(fn ($label, $key) => $isCustomKey($key) && old('column_visibility.'.$key, '1') != '0')
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => (string) ($storedColumns->get($key)['label'] ?? $label),
                'visible' => true,
                'order' => (int) old('column_order.'.$key, 100),
                'group' => null,
                'custom' => true,
            ])
        : $storedColumns
            ->filter(fn ($stored, $key) => ($stored['custom'] ?? false) === true && $isCustomKey($key))
            ->map(fn ($stored, $key) => [
                'key' => $key,
                'label' => (string) ($stored['label'] ?? ''),
                'visible' => true,
                'order' => (int) ($stored['order'] ?? 100),
                'group' => null,
                'custom' => true,
            ]);

    $editorColumns = $editorColumns
        ->concat($customColumns->values())
        ->sortBy('order')
        ->values();

    $reportLabel = $reportTypes[$reportKey] ?? 'Report Form';

    $isDraft = $selectedTemplate?->status === 'DRAFT';
    $isActive = $selectedTemplate?->status === 'ACTIVE';
    $isArchived = $selectedTemplate?->status === 'ARCHIVED';

    /*
     * Only the working Draft, or the Active revision while no Draft exists,
     * can be edited. Saving from the Active revision creates the Draft.
     */
    $canEdit = $isDraft || ($isActive && ! $draftTemplate);

    $statusTone = fn (?string $status) => match ($status) {
        'ACTIVE' => 'is-active',
        'DRAFT' => 'is-draft',
        default => 'is-archived',
    };

    $statusLabel = fn (?string $status) => match ($status) {
        'ACTIVE' => 'Active',
        'DRAFT' => 'Working Draft',
        'ARCHIVED' => 'Archived',
        default => (string) $status,
    };

    $personName = fn ($template) => $template?->configuredBy?->full_name
        ?? $template?->configuredBy?->name
        ?? 'System';

    $checklistReady = collect($activationChecklist ?? [])->every(fn ($item) => $item['ok']);

    $pageSize = old('page_size', $page['size'] ?? 'A4');
    $orientation = old('orientation', $page['orientation'] ?? 'LANDSCAPE');

    $sections = [
        'document' => ['Document Details', 'Form code, revision and effectivity'],
        'columns' => ['Table Columns', 'Headings, order and visibility'],
        'performance' => ['Performance Summary', 'Show or hide the KPI'],
        'signatories' => ['Signatories', 'Prepared by and noted by'],
        'page' => ['Page Setup', 'Paper size and orientation'],
    ];

    $fieldError = fn (string $name) => $errors->has($name) ? 'has-error' : '';
@endphp


<section class="page-heading">
    <div>
        <p class="eyebrow">Configuration</p>
        <h1>Report Form Configuration</h1>
        <p>Control the layout of official SPMU reports. Every change goes through a Draft first, so the form in use never changes until you activate it.</p>
    </div>

    <div class="actions">
        <a class="button secondary ui-pressable" href="{{ route('policies.index') }}">Back</a>
    </div>
</section>


<section class="content-area rfc" data-rfc>

    {{-- Success and validation notices are rendered once by layouts.app; this page adds none of its own. --}}




    @if(! $selectedTemplate)

        <article class="card rfc-empty">
            <h2>No report form revision yet</h2>
            <p>Run the database migrations to install the official CSPC-F-SPMU-22 form.</p>
        </article>

    @else

        {{-- ============================================================
             Overview
             ============================================================ --}}
        <article class="card rfc-overview">

            <div class="rfc-overview-top">
                <div class="rfc-overview-title">
                    {{-- Configuration-page naming only. The official report title printed on the form is not set here. --}}
                    @php
                        $overviewTitle = [
                            'SUMMARY_REQUESTS_EQUIPMENT' => 'Equipment Request Summary',
                        ][$reportKey] ?? $reportLabel;
                    @endphp
                    <p class="eyebrow">Official report template</p>
                    <h2>{{ $overviewTitle }}</h2>
                    <p class="rfc-overview-sub">Official report: {{ $reportLabel }}</p>
                </div>

                <div class="rfc-overview-badges">
                    <span class="rfc-status {{ $statusTone($selectedTemplate->status) }}">
                        {{ $statusLabel($selectedTemplate->status) }}
                    </span>
                    <span class="rfc-version">Version {{ $selectedTemplate->version_no }}</span>
                </div>

                @if(count($reportTypes) > 1)
                    <label class="rfc-report-type">
                        <span>Report</span>
                        <select onchange="window.location.href='{{ route('policies.report-forms.index') }}?report=' + encodeURIComponent(this.value)">
                            @foreach($reportTypes as $key => $label)
                                <option value="{{ $key }}" @selected($reportKey === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>

            <dl class="rfc-meta">
                <div>
                    <dt>Form Code</dt>
                    <dd>{{ $selectedTemplate->form_code ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Form Revision</dt>
                    <dd>{{ $selectedTemplate->revision_label ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Effectivity</dt>
                    <dd>{{ $selectedTemplate->effectivity_date?->format('d M Y') ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Last modified</dt>
                    <dd>
                        {{ $personName($selectedTemplate) }}
                        <small>{{ $selectedTemplate->updated_at?->format('d M Y, g:i A') }}</small>
                    </dd>
                </div>
            </dl>

        </article>


        {{-- ============================================================
             Context: what can be done with this revision
             ============================================================ --}}
        @if($isActive && $draftTemplate)

            <div class="rfc-banner is-warning">
                <div>
                    <strong>A working Draft (Version {{ $draftTemplate->version_no }}) is in progress.</strong>
                    <span>This Active revision is shown read-only. Continue your changes in the Draft so nothing is overwritten.</span>
                </div>
                <a class="button primary ui-pressable" href="{{ route('policies.report-forms.index', ['report' => $reportKey, 'template' => $draftTemplate->id]) }}">
                    Open Working Draft
                </a>
            </div>

        @elseif($isActive)

            <div class="rfc-banner is-info">
                <div>
                    <strong>This is the official form currently in use.</strong>
                    <span>Edit any section below and save. Your changes become a separate Draft. The official form stays the same until you activate the Draft.</span>
                </div>
            </div>

        @elseif(! $isDraft)

            <div class="rfc-banner is-neutral">
                <div>
                    <strong>Archived revision. Read-only.</strong>
                    <span>Archived revisions are kept for audit. To reuse this layout, copy it into a new Draft.</span>
                </div>
                <div class="rfc-banner-actions">
                    @if($activeTemplate)
                        <a class="button secondary ui-pressable" href="{{ route('policies.report-forms.index', ['report' => $reportKey, 'template' => $activeTemplate->id]) }}">View Active</a>
                    @endif
                    <form method="post" action="{{ route('policies.report-forms.duplicate', $selectedTemplate) }}">
                        @csrf
                        <button class="button primary ui-pressable" type="submit">Use as New Revision</button>
                    </form>
                </div>
            </div>

        @endif


        {{-- ============================================================
             Tabs
             ============================================================ --}}
        <div class="rfc-tabs" role="tablist" aria-label="Report form configuration">
            <button class="rfc-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="rfc-panel-layout" id="rfc-tab-layout" data-rfc-tab="layout">
                {{ $canEdit ? 'Edit Layout' : 'Layout' }}
            </button>
            <button class="rfc-tab" type="button" role="tab" aria-selected="false" aria-controls="rfc-panel-preview" id="rfc-tab-preview" data-rfc-tab="preview">
                Preview
            </button>
            <button class="rfc-tab" type="button" role="tab" aria-selected="false" aria-controls="rfc-panel-history" id="rfc-tab-history" data-rfc-tab="history">
                Revision History
                <span class="rfc-tab-count">{{ $templates->count() }}</span>
            </button>
        </div>


        {{-- ============================================================
             Layout editor
             ============================================================ --}}
        <div class="rfc-panel" id="rfc-panel-layout" role="tabpanel" aria-labelledby="rfc-tab-layout" data-rfc-panel="layout">
            <div class="rfc-workspace">

                <nav class="rfc-nav" aria-label="Form sections">
                    <p class="rfc-nav-title">Sections</p>
                    @foreach($sections as $id => [$title, $hint])
                        <a href="#rfc-section-{{ $id }}" data-rfc-nav="{{ $id }}">
                            <span class="rfc-nav-num">{{ $loop->iteration }}</span>
                            <span class="rfc-nav-text">
                                <strong>{{ $title }}</strong>
                                <small>{{ $hint }}</small>
                            </span>
                            <span class="rfc-nav-dot" title="Unsaved changes" aria-hidden="true"></span>
                        </a>
                    @endforeach
                </nav>

                <form
                    method="post"
                    action="{{ route('policies.report-forms.store') }}"
                    class="rfc-editor {{ $canEdit ? '' : 'is-readonly' }}"
                    autocomplete="off"
                    novalidate
                    data-rfc-editor
                    data-has-errors="{{ $errors->any() ? '1' : '0' }}"
                    data-readonly="{{ $canEdit ? '0' : '1' }}"
                >
                    @csrf
                    <input type="hidden" name="source_template_id" value="{{ $selectedTemplate->id }}">
                    <input type="hidden" name="report_key" value="{{ $reportKey }}">

                    @unless($canEdit)
                        <p class="rfc-readonly-note">
                            <span aria-hidden="true">🔒</span>
                            Read-only view of Version {{ $selectedTemplate->version_no }}.
                        </p>
                    @endunless


                    {{-- 1 · Document details --}}
                    <section class="card rfc-section" id="rfc-section-document" data-rfc-section="document" data-rfc-section-title="Document Details">
                        <header class="rfc-section-head">
                            <span class="rfc-section-num" aria-hidden="true">1</span>
                            <div>
                                <h2>Document Details</h2>

                            </div>
                        </header>

                        <div class="rfc-grid rfc-grid-3">
                            <label class="rfc-field {{ $fieldError('form_code') }}">
                                <span>Form Code </span>
                                <input type="text" name="form_code" maxlength="80"
                                    value="{{ old('form_code', $selectedTemplate->form_code) }}"
                                    placeholder="e.g. CSPC-F-SPMU-22"
                                    @disabled(! $canEdit) data-preview="form_code">
                            </label>

                            <label class="rfc-field {{ $fieldError('revision_label') }}">
                                <span>Form Revision </span>
                                <input type="text" name="revision_label" maxlength="50" placeholder="e.g. Rev. 3"
                                    value="{{ old('revision_label', $selectedTemplate->revision_label) }}"
                                    @disabled(! $canEdit) data-preview="revision_label">
                            </label>

                            <label class="rfc-field {{ $fieldError('effectivity_date') }}">
                                <span>Effectivity Date </span>
                                <input type="date" name="effectivity_date"
                                    value="{{ old('effectivity_date', $selectedTemplate->effectivity_date?->format('Y-m-d')) }}"
                                    @disabled(! $canEdit) data-preview="effectivity_date">
                            </label>
                        </div>
                    </section>


                     {{-- Institutional header and CSPC seal are fixed in the official template. --}}

                    {{-- 3 · Columns --}}
                    <section class="card rfc-section" id="rfc-section-columns" data-rfc-section="columns" data-rfc-section-title="Table Columns">
                        <header class="rfc-section-head">
                            <span class="rfc-section-num" aria-hidden="true">2</span>
                            <div>
                                <h2>Table Columns</h2>
                                <p>Add, rename, reorder, or remove report columns.</p>
                            </div>
                            <span class="rfc-pill" data-rfc-column-count></span>
                        </header>

                        @if($canEdit)
                            {{-- One control for both kinds of column: pick a system field, or create a custom heading. --}}
                            <div class="rfc-add-column" data-rfc-add-toolbar>
                                <label for="rfc-add-search">Add Column</label>
                                <div class="rfc-combo">
                                    <input type="text" id="rfc-add-search" maxlength="120" autocomplete="off" spellcheck="false"
                                        placeholder="Search a system field or type a custom heading..."
                                        role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="rfc-add-options"
                                        aria-describedby="rfc-custom-hint" data-rfc-add-search>
                                    <ul class="rfc-combo-list" id="rfc-add-options" role="listbox" aria-label="Columns that can be added" data-rfc-add-options hidden></ul>
                                </div>
                            </div>
                            <p class="rfc-inline-error" data-rfc-custom-error hidden role="alert"></p>
                        @endif

                        <div class="rfc-columns-wrap">
                            <table class="rfc-columns">
                                <thead>
                                    <tr>
                                        <th class="rfc-col-move"><span class="visually-hidden">Order</span></th>
                                        <th class="rfc-col-pos">#</th>
                                        <th>Column heading</th>
                                        <th class="rfc-col-group">Source</th>
                                        <th class="rfc-col-include">Action</th>
                                    </tr>
                                </thead>

                                <tbody data-rfc-columns>
                                    {{-- The last entry is the blank row the editor clones for a new custom column. --}}
                                    @php
                                        $rowTemplate = ['key' => '__custom__', 'label' => '', 'visible' => true, 'order' => 100, 'group' => null, 'custom' => true];
                                    @endphp
                                    @foreach($canEdit ? $editorColumns->concat([$rowTemplate]) : $editorColumns as $column)
                                        @php
                                            $isRowTemplate = $column['key'] === '__custom__';
                                            $visible = $isRowTemplate || (bool) old('column_visibility.'.$column['key'], $column['visible']);
                                        @endphp
                                        @if($isRowTemplate)<template data-rfc-custom-template>@endif
                                        <tr
                                            data-column-row
                                            data-column-key="{{ $column['key'] }}"
                                            data-custom="{{ $column['custom'] ? '1' : '' }}"
                                            data-group="{{ $column['group'] ?? '' }}"
                                            class="{{ $column['group'] ? 'is-grouped is-group-'.strtolower($column['group']) : '' }} {{ $visible ? '' : 'is-excluded' }}"
                                        >
                                            <td class="rfc-col-move">
                                                @if($canEdit)
                                                    <div class="rfc-move">
                                                        <span class="rfc-drag" draggable="true" title="Drag to reorder" aria-hidden="true">⠿</span>
                                                        <button type="button" class="rfc-arrow" data-rfc-move="-1" aria-label="Move {{ $column['label'] }} up">▲</button>
                                                        <button type="button" class="rfc-arrow" data-rfc-move="1" aria-label="Move {{ $column['label'] }} down">▼</button>
                                                    </div>
                                                @endif
                                                <input type="hidden" name="column_order[{{ $column['key'] }}]"
                                                    value="{{ old('column_order.'.$column['key'], $column['order']) }}"
                                                    data-column-order @disabled(! $canEdit)>
                                            </td>

                                            <td class="rfc-col-pos" data-column-position>{{ $loop->iteration }}</td>

                                            <td>
                                                <input type="text" class="rfc-column-input"
                                                    name="column_labels[{{ $column['key'] }}]" maxlength="120" required
                                                    value="{{ old('column_labels.'.$column['key'], $column['label']) }}"
                                                    aria-label="Heading for column {{ $loop->iteration }}"
                                                    @disabled(! $canEdit) data-column-label>
                                            </td>

                                            <td class="rfc-col-group">
                                                @if($column['group'])
                                                    <span class="rfc-group-chip is-{{ strtolower($column['group']) }}">{{ ucfirst(strtolower($column['group'])) }}</span>
                                                @elseif($column['custom'])
                                                    <span class="rfc-source-chip is-custom" title="Not linked to system data. Its cells print blank.">Custom</span>
                                                @else
                                                    <span class="rfc-source-chip" title="Filled automatically from system records.">System</span>
                                                @endif
                                            </td>

                                            <td class="rfc-col-include">
                                                <input type="hidden" name="column_visibility[{{ $column['key'] }}]" value="0" @disabled(! $canEdit)>
                                                <input type="checkbox" class="rfc-column-included-input" name="column_visibility[{{ $column['key'] }}]" value="1"
                                                    @checked($visible) @disabled(! $canEdit) tabindex="-1"
                                                    aria-label="Include {{ $column['label'] }}" data-column-visible>
                                                @if($canEdit)
                                                    <button class="rfc-remove-col" type="button" data-rfc-remove-column
                                                        aria-label="Remove {{ $column['label'] }}" title="Remove column from printed form"
                                                        @disabled($column['key'] === 'control_no')>
                                                        Remove
                                                    </button>
                                                @else
                                                    <span class="rfc-pill">{{ $visible ? 'Included' : 'Not included' }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @if($isRowTemplate)</template>@endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="rfc-help">Add/Remove affects the official document layout, not stored transactions. Control No. is mandatory, but its heading can be renamed. <span id="rfc-custom-hint">System columns are filled from system records; custom columns print blank cells for handwritten entries.</span></p>
                        <p class="rfc-layout-warning" data-rfc-layout-warning hidden role="status">This form has more than 10 included columns. Check Full Preview and printed/PDF output before activating; narrow paper layouts may overflow.</p>
                        <p class="rfc-inline-error" data-rfc-column-error hidden role="alert">At least one column must stay included.</p>
                    </section>


                    {{-- 4 · Performance --}}
                    <section class="card rfc-section" id="rfc-section-performance" data-rfc-section="performance" data-rfc-section-title="Performance Summary">
                        <header class="rfc-section-head">
                            <span class="rfc-section-num" aria-hidden="true">3</span>
                            <div>
                                <h2>Performance Summary</h2>
                                <p>The KPI line printed below the table.</p>
                            </div>
                        </header>

                        <label class="rfc-switch">
                            <input type="hidden" name="formula_enabled" value="0" @disabled(! $canEdit)>
                            <input type="checkbox" name="formula_enabled" value="1"
                                @checked(old('formula_enabled', $formula['enabled'] ?? true))
                                @disabled(! $canEdit) data-preview="formula_enabled">
                            <span class="rfc-switch-track" aria-hidden="true"></span>
                            <span>Show the performance summary on the official report</span>
                        </label>

                        <div class="rfc-protected">
                            <div>
                                <span class="rfc-protected-label">Formula · system-controlled</span>
                                <strong>{{ $formula['numerator_label'] ?? 'Number of requests responded on time' }} ÷ {{ $formula['denominator_label'] ?? 'Total number of requests' }} × 100%</strong>
                                <small>You can show or hide this line. The KPI definition, totals and result are computed by the system and cannot be edited here.</small>
                            </div>
                            <span class="rfc-lock" aria-hidden="true">🔒</span>
                        </div>
                    </section>


                    {{-- 5 · Signatories --}}
                    <section class="card rfc-section" id="rfc-section-signatories" data-rfc-section="signatories" data-rfc-section-title="Signatories">
                        <header class="rfc-section-head">
                            <span class="rfc-section-num" aria-hidden="true">4</span>
                            <div>
                                <h2>Signatories</h2>
                                <p>Printed names under the signature lines. The form is signed by hand after printing.</p>
                            </div>
                        </header>

                        <div class="rfc-grid rfc-grid-2">
                            @foreach([
                                'prepared_by' => ['Prepared by', 'Prepared by'],
                                'noted_by' => ['Noted by', 'Noted by'],
                            ] as $prefix => [$heading, $defaultLabel])
                                <fieldset class="rfc-signatory">
                                    <legend>{{ $heading }}</legend>

                                    <label class="rfc-field {{ $fieldError($prefix.'_label') }}">
                                        <span>Label printed on form</span>
                                        <input type="text" name="{{ $prefix }}_label" maxlength="80" required
                                            value="{{ old($prefix.'_label', $signatories[$prefix.'_label'] ?? $defaultLabel) }}"
                                            @disabled(! $canEdit) data-preview="{{ $prefix }}_label">
                                    </label>

                                    <label class="rfc-field {{ $fieldError($prefix.'_name') }}">
                                        <span>Printed name <em>Required to activate</em></span>
                                        <input type="text" name="{{ $prefix }}_name" maxlength="150"
                                            value="{{ old($prefix.'_name', $signatories[$prefix.'_name'] ?? '') }}"
                                            @disabled(! $canEdit) data-preview="{{ $prefix }}_name">
                                    </label>

                                    <label class="rfc-field {{ $fieldError($prefix.'_designation') }}">
                                        <span>Designation <em>Required to activate</em></span>
                                        <input type="text" name="{{ $prefix }}_designation" maxlength="150"
                                            value="{{ old($prefix.'_designation', $signatories[$prefix.'_designation'] ?? '') }}"
                                            @disabled(! $canEdit) data-preview="{{ $prefix }}_designation">
                                    </label>
                                </fieldset>
                            @endforeach
                        </div>

                        <label class="rfc-switch">
                            <input type="hidden" name="show_signature_date" value="0" @disabled(! $canEdit)>
                            <input type="checkbox" name="show_signature_date" value="1"
                                @checked(old('show_signature_date', $signatories['show_date'] ?? true))
                                @disabled(! $canEdit) data-preview="show_signature_date">
                            <span class="rfc-switch-track" aria-hidden="true"></span>
                            <span>Print a "Date: ____" line below each signatory</span>
                        </label>
                    </section>


                    {{-- 6 · Page setup --}}
                    <section class="card rfc-section" id="rfc-section-page" data-rfc-section="page" data-rfc-section-title="Page Setup">
                        <header class="rfc-section-head">
                            <span class="rfc-section-num" aria-hidden="true">5</span>
                            <div>
                                <h2>Page Setup</h2>
                                <p>Used for the printable view and the PDF export.</p>
                            </div>
                        </header>

                        <div class="rfc-grid rfc-grid-2">
                            <fieldset class="rfc-choice-group">
                                <legend>Paper size</legend>
                                <div class="rfc-choices">
                                    @foreach([
                                        'A4' => ['A4', '210 × 297 mm'],
                                        'LETTER' => ['Letter', '8.5 × 11 in'],
                                        'LEGAL' => ['Legal', '8.5 × 14 in'],
                                    ] as $value => [$label, $dimensions])
                                        <label class="rfc-choice">
                                            <input type="radio" name="page_size" value="{{ $value }}" required
                                                @checked($pageSize === $value) @disabled(! $canEdit) data-preview="page_size">
                                            <span>
                                                <strong>{{ $label }}</strong>
                                                <small>{{ $dimensions }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <fieldset class="rfc-choice-group">
                                <legend>Orientation</legend>
                                <div class="rfc-choices">
                                    @foreach(['LANDSCAPE' => 'Landscape', 'PORTRAIT' => 'Portrait'] as $value => $label)
                                        <label class="rfc-choice">
                                            <input type="radio" name="orientation" value="{{ $value }}" required
                                                @checked($orientation === $value) @disabled(! $canEdit) data-preview="orientation">
                                            <span>
                                                <i class="rfc-sheet-icon is-{{ strtolower($value) }}" aria-hidden="true"></i>
                                                <strong>{{ $label }}</strong>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        </div>
                    </section>


                    {{--
                        Save card. On a working Draft it is one of two states of a
                        single workflow: shown only while there are unsaved changes,
                        and replaced by Review & Activate once the Draft is saved.
                    --}}
                    @if($canEdit)
                        <div class="rfc-savebar" data-rfc-savebar @if($isDraft && ! $errors->any()) hidden @endif>
                            <div class="rfc-savebar-state">
                                <span class="rfc-state-dot" aria-hidden="true"></span>
                                <div>
                                    <strong data-rfc-state>{{ $errors->any() ? 'Unsaved changes' : 'No unsaved changes' }}</strong>
                                    <small data-rfc-state-detail>
                                        {{ $isDraft ? 'Saving updates this working Draft.' : 'Saving creates a working Draft. The Active form is not changed.' }}
                                    </small>
                                </div>
                            </div>

                            <div class="rfc-savebar-actions">
                                <button class="button secondary ui-pressable" type="button" data-rfc-reset hidden>Undo Changes</button>
                                <button class="button primary ui-pressable" type="submit" data-rfc-save disabled>
                                    {{ $isDraft ? 'Save Draft' : 'Save as Draft' }}
                                </button>
                            </div>
                        </div>
                    @endif
                </form>

                {{--
                    Review & Activate: the saved-Draft state of the workflow. It
                    takes the place of the save card while nothing is unsaved.
                --}}
                @if($isDraft)
                    @php
                        $checklistShort = [
                            'form_code' => 'Form Code',
                            'revision_label' => 'Form Revision',
                            'effectivity_date' => 'Effectivity Date',
                            'title_template' => 'Report Title',
                            'columns' => 'Table columns',
                            'signatories' => 'Signatories (printed name and designation for both)',
                        ];
                        $checklistTotal = count($activationChecklist);
                        $checklistMissing = collect($activationChecklist)->reject(fn ($item) => $item['ok'])->values();
                        $checklistDone = $checklistTotal - $checklistMissing->count();
                    @endphp
                    <article class="card rfc-review {{ $checklistReady ? 'is-ready' : 'is-incomplete' }}" id="rfc-review" data-rfc-review @if($errors->any()) hidden @endif>
                        <header class="rfc-review-head">
                            <div>
                                <h2>Review &amp; Activate</h2>
                                <small>Working Draft · Version {{ $selectedTemplate->version_no }}</small>
                            </div>
                            <span class="rfc-pill rfc-readiness {{ $checklistReady ? 'is-ok' : 'is-missing' }}" role="status">
                                {{ $checklistDone }}/{{ $checklistTotal }} Complete
                            </span>
                        </header>

                        @unless($checklistReady)
                            <div class="rfc-review-missing" id="rfc-review-missing">
                                <strong>Required before activation</strong>
                                <ul>
                                    @foreach($checklistMissing as $item)
                                        <li>{{ $checklistShort[$item['key']] ?? $item['label'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endunless

                        <details class="rfc-review-details">
                            <summary>All requirements</summary>
                            <ul class="rfc-checklist" aria-label="Activation requirements">
                                @foreach($activationChecklist as $item)
                                    <li class="{{ $item['ok'] ? 'is-ok' : 'is-missing' }}">
                                        <span aria-hidden="true">{{ $item['ok'] ? '✓' : '!' }}</span>
                                        {{ $item['label'] }}
                                        <span class="visually-hidden">{{ $item['ok'] ? '(complete)' : '(missing)' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>

                        <div class="rfc-review-actions">
                            <button class="button secondary ui-pressable" type="button" data-rfc-show-tab="preview">
                                Preview Draft
                            </button>

                            <form method="post" action="{{ route('policies.report-forms.discard', $selectedTemplate) }}">
                                @csrf
                                <button
                                    class="button rfc-button-danger-outline ui-pressable"
                                    type="submit"
                                    data-rfc-confirm="Discard working Draft Version {{ $selectedTemplate->version_no }}?&#10;&#10;All changes in this Draft will be deleted. The Active form will not change."
                                >
                                    Discard Draft
                                </button>
                            </form>

                            <form method="post" action="{{ route('policies.report-forms.activate', $selectedTemplate) }}">
                                @csrf
                                <button
                                    class="button primary ui-pressable"
                                    type="submit"
                                    data-rfc-confirm="Activate Version {{ $selectedTemplate->version_no }} as the official report form?&#10;&#10;The current Active revision will be archived and all new official reports will use this layout."
                                    data-rfc-requires-saved
                                    data-rfc-ready="{{ $checklistReady ? '1' : '0' }}"
                                    @unless($checklistReady) aria-describedby="rfc-review-missing" title="Complete the required items and save the Draft first." @endunless
                                    @disabled(! $checklistReady)
                                >
                                    Activate Version {{ $selectedTemplate->version_no }}
                                </button>
                            </form>
                        </div>
                    </article>
                @endif

                <aside class="rfc-live" aria-label="Live official form preview" data-rfc-live-preview>
                    <div class="rfc-live-head">
                        <div>
                            <strong>Live Preview</strong>
                            <small>Sample data · Changes are not saved</small>
                        </div>
                        <button type="button" class="button secondary small ui-pressable" data-rfc-show-tab="preview">Full Preview</button>
                    </div>
                    <div class="rfc-live-toolbar" role="toolbar" aria-label="Live preview zoom controls" data-rfc-zoom-toolbar>
                        <button type="button" class="rfc-live-zoom-button" data-rfc-zoom-out aria-label="Zoom out" title="Zoom out">−</button>
                        <output class="rfc-live-zoom-label" data-rfc-zoom-label aria-live="off">Fit</output>
                        <button type="button" class="rfc-live-zoom-button" data-rfc-zoom-in aria-label="Zoom in" title="Zoom in">+</button>
                        <span class="rfc-live-toolbar-divider" aria-hidden="true"></span>
                        <button type="button" class="rfc-live-zoom-fit" data-rfc-fit-width aria-pressed="true" title="Fit the full document width">Fit Width</button>
                        <button type="button" class="rfc-live-zoom-fit" data-rfc-fit-page aria-pressed="false" title="Fit the entire page">Fit Page</button>
                    </div>
                    <div class="rfc-live-content" data-rfc-live-content aria-label="Zoomable document preview" tabindex="0"></div>
                    <p class="rfc-live-note">Only a saved and activated revision changes the official form used for Print and Export.</p>
                </aside>
            </div>
        </div>


        {{-- ============================================================
             Preview
             ============================================================ --}}
        <div class="rfc-panel" id="rfc-panel-preview" role="tabpanel" aria-labelledby="rfc-tab-preview" data-rfc-panel="preview" hidden>
            <article class="card rfc-preview-card">
                <header class="rfc-preview-head">
                    <div>
                        <h2>Preview</h2>
                        <p>Shows the layout with sample data, including edits you have not saved yet. Real reports are filled in by the system.</p>
                    </div>
                    <span class="rfc-pill" data-rfc-paper-meta>A4 · Landscape</span>
                </header>

                <div class="rfc-preview-scroll">
                    <div class="rfc-paper" data-rfc-paper>
                        <div class="rfc-paper-header">
                            <div class="rfc-paper-brand">
                                <img class="rfc-paper-logo" src="{{ asset('images/cspc-logo.png') }}" alt="CSPC" data-out="logo">
                                <div>
                                    <small data-out="header_republic"></small>
                                    <strong data-out="header_institution"></strong>
                                    <small data-out="header_location"></small>
                                </div>
                            </div>
                            <strong class="rfc-paper-code" data-out="form_code"></strong>
                        </div>

                        <div class="rfc-paper-rule"></div>

                        <h3 class="rfc-paper-title" data-out="title"></h3>

                        <table class="rfc-paper-table">
                            <thead data-out="table_head"></thead>
                            <tbody data-out="table_body"></tbody>
                        </table>

                        <p class="rfc-paper-formula" data-out="formula">
                            <strong>Formula:</strong>
                            {{ $formula['numerator_label'] ?? 'Number of requests responded on time' }}
                            / {{ $formula['denominator_label'] ?? 'Total number of requests' }} × 100%
                            · Total requests: <strong>2</strong> · On-time result: <strong>Not evaluated</strong>
                        </p>

                        <div class="rfc-paper-signatures">
                            @foreach(['prepared_by', 'noted_by'] as $prefix)
                                <div>
                                    <span data-out="{{ $prefix }}_label"></span>
                                    <div class="rfc-paper-ink" aria-hidden="true"></div>
                                    <strong data-out="{{ $prefix }}_name"></strong>
                                    <small data-out="{{ $prefix }}_designation"></small>
                                    <small data-out="signature_date">Date: __________________</small>
                                </div>
                            @endforeach
                        </div>

                        <div class="rfc-paper-footer">
                            <span>Effectivity Date: <strong data-out="effectivity"></strong></span>
                            <span data-out="revision_label"></span>
                            <span>Page 1 of 1</span>
                        </div>
                    </div>
                </div>
            </article>
        </div>


        {{-- ============================================================
             History
             ============================================================ --}}
        <div class="rfc-panel" id="rfc-panel-history" role="tabpanel" aria-labelledby="rfc-tab-history" data-rfc-panel="history" hidden>
            <article class="card rfc-history-card">
                <header class="rfc-preview-head">
                    <div>
                        <h2>Revision History</h2>
                        <p>Active and Archived revisions cannot be changed. They are kept for audit.</p>
                    </div>
                </header>

                <div class="rfc-history-wrap">
                    <table class="rfc-history">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Form Revision</th>
                                <th>Effectivity</th>
                                <th>Changed by</th>
                                <th><span class="visually-hidden">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($templates as $template)
                                @php $isSelected = (int) $template->id === (int) $selectedTemplate->id; @endphp
                                <tr class="{{ $isSelected ? 'is-selected' : '' }}">
                                    <td><strong>{{ $template->version_no }}</strong></td>
                                    <td>
                                        <span class="rfc-status {{ $statusTone($template->status) }}">{{ $statusLabel($template->status) }}</span>
                                        @if($template->activated_at)
                                            <small class="rfc-history-sub">Activated {{ $template->activated_at->format('d M Y, g:i A') }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $template->revision_label ?: '—' }}</td>
                                    <td>{{ $template->effectivity_date?->format('d M Y') ?? '—' }}</td>
                                    <td>
                                        {{ $personName($template) }}
                                        <small class="rfc-history-sub">{{ $template->updated_at?->format('d M Y, g:i A') }}</small>
                                    </td>
                                    <td class="rfc-history-action">
                                        @if($isSelected)
                                            <span class="rfc-muted">Viewing</span>
                                        @else
                                            <a class="button secondary small ui-pressable" href="{{ route('policies.report-forms.index', ['report' => $reportKey, 'template' => $template->id]) }}">
                                                {{ $template->status === 'DRAFT' ? 'Open' : 'View' }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        </div>

    @endif
</section>


<style>
/* REPORT FORM CONFIGURATION (rfc) */
.rfc{display:grid;gap:16px;min-width:0}
.rfc .card{margin:0}
.rfc h2{margin:0;color:var(--heading);font-size:17px;font-weight:750;letter-spacing:-.01em}
.rfc p{margin:0}
.rfc .visually-hidden{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}

/* Flash */
.rfc-flash{display:flex;gap:12px;align-items:flex-start;padding:12px 16px;border:1px solid;border-radius:var(--radius);font-size:13px}
.rfc-flash ul{margin:6px 0 0;padding-left:18px}
.rfc-flash.is-success{color:var(--success);background:var(--success-bg);border-color:var(--success-border)}
.rfc-flash.is-danger{color:var(--danger);background:var(--danger-bg);border-color:var(--danger-border)}
.rfc-flash-icon{display:inline-grid;place-items:center;flex:0 0 22px;height:22px;border-radius:50%;background:currentColor;font-weight:800;font-size:12px}
.rfc-flash-icon{color:inherit}
.rfc-flash.is-success .rfc-flash-icon{background:var(--success);color:var(--success-bg)}
.rfc-flash.is-danger .rfc-flash-icon{background:var(--danger);color:var(--danger-bg)}

/* Overview */
.rfc-overview{display:grid;gap:16px}
.rfc-overview-top{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px}
.rfc-overview-title{flex:1 1 auto;min-width:0}
.rfc-overview-title h2{font-size:20px}
.rfc-overview-sub{margin:2px 0 0;color:var(--text-muted);font-size:12px}
.rfc-overview-badges{display:flex;align-items:center;gap:8px}
.rfc-version{color:var(--text-muted);font-size:12px;font-weight:700}
.rfc-report-type{display:flex;align-items:center;gap:8px}
.rfc-report-type select{min-width:240px}
.rfc-status{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border:1px solid;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.02em;white-space:nowrap}
.rfc-status::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.rfc-status.is-active{color:var(--success);background:var(--success-bg);border-color:var(--success-border)}
.rfc-status.is-draft{color:var(--warning);background:var(--warning-bg);border-color:var(--warning-border)}
.rfc-status.is-archived{color:var(--neutral);background:var(--neutral-bg);border-color:var(--neutral-border)}
.rfc-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0}
.rfc-meta>div{padding:10px 12px;border:1px solid var(--row-border);border-radius:var(--radius-sm);background:var(--surface-subtle)}
.rfc-meta dt{color:var(--text-muted);font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.rfc-meta dd{margin:4px 0 0;color:var(--heading);font-size:14px;font-weight:700;overflow-wrap:anywhere}
.rfc-meta dd small{display:block;margin-top:2px;color:var(--text-muted);font-size:11px;font-weight:500}

/* Steps */

/* Banner and review */
.rfc-banner{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px 20px;padding:14px 16px;border:1px solid;border-left-width:4px;border-radius:var(--radius);font-size:13px}
.rfc-banner strong{display:block;margin-bottom:2px}
.rfc-banner span{color:var(--text-secondary)}
.rfc-banner.is-info{background:var(--info-bg);border-color:var(--info-border);border-left-color:var(--info);color:var(--info)}
.rfc-banner.is-warning{background:var(--warning-bg);border-color:var(--warning-border);border-left-color:var(--warning);color:var(--warning)}
.rfc-banner.is-neutral{background:var(--neutral-bg);border-color:var(--neutral-border);border-left-color:var(--neutral);color:var(--neutral)}
.rfc-banner-actions{display:flex;flex-wrap:wrap;gap:8px}
.rfc-banner-actions form,.rfc-review-actions form{margin:0}

.rfc-review{grid-column:1;grid-row:3;min-width:0;display:grid;gap:12px;border-left:4px solid var(--warning)}
.rfc-review-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px 12px}
.rfc-review-head h2{margin:0;font-size:16px}
.rfc-checklist{display:flex;flex-wrap:wrap;gap:6px 16px;margin:0;padding:0;list-style:none;font-size:12.5px}
.rfc-checklist li{display:flex;align-items:center;gap:6px;color:var(--text-secondary);white-space:nowrap}
.rfc-checklist li>span:first-child{display:inline-grid;place-items:center;flex:0 0 20px;height:20px;border-radius:50%;font-size:11px;font-weight:800}
.rfc-checklist li.is-ok>span:first-child{background:var(--success-bg);color:var(--success);border:1px solid var(--success-border)}
.rfc-checklist li.is-missing{color:var(--danger);font-weight:600}
.rfc-checklist li.is-missing>span:first-child{background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border)}
.rfc-review-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;padding-top:12px;border-top:1px solid var(--row-border)}
.rfc-review[hidden]{display:none}
.rfc-review.is-ready{border-left-color:var(--success)}
.rfc-review.is-incomplete{border-left-color:var(--danger)}
.rfc-review-head small{display:block;margin-top:2px;color:var(--text-muted);font-size:12px}
.rfc-readiness.is-ok{background:var(--success-bg);color:var(--success)}
.rfc-readiness.is-missing{background:var(--danger-bg);color:var(--danger)}
.rfc-review-missing{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 12px;padding:8px 12px;border-radius:var(--radius-sm);background:var(--danger-bg);color:var(--danger);font-size:12.5px}
.rfc-review-missing ul{display:flex;flex-wrap:wrap;gap:2px 16px;margin:0;padding:0;list-style:none;font-weight:600}
.rfc-review-missing li::before{content:"!";display:inline-grid;place-items:center;width:16px;height:16px;margin-right:6px;border:1px solid var(--danger-border);border-radius:50%;font-size:10px;font-weight:800}
.rfc-review-details{font-size:12px}
.rfc-review-details summary{width:fit-content;color:var(--text-muted);font-weight:600;cursor:pointer}
.rfc-review-details[open] summary{margin-bottom:8px}
.rfc-review-details .rfc-checklist li{white-space:normal}
.rfc .button.rfc-button-danger-outline{color:var(--danger);background:var(--surface-elevated);border-color:var(--danger-border)}
.rfc .button.rfc-button-danger-outline:hover{color:#fff;background:var(--danger-action);border-color:var(--danger-action)}

/* Tabs */
.rfc-tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);overflow-x:auto}
.rfc-tab{position:relative;display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border:0;background:none;color:var(--text-muted);font:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap}
.rfc-tab:hover{color:var(--heading)}
.rfc-tab.is-active{color:var(--interactive)}
.rfc-tab.is-active::after{content:"";position:absolute;left:8px;right:8px;bottom:-1px;height:3px;border-radius:3px 3px 0 0;background:var(--interactive)}
.rfc-tab:focus-visible{outline:0;box-shadow:var(--focus-ring);border-radius:var(--radius-sm)}
.rfc-tab-count{padding:1px 7px;border-radius:999px;background:var(--surface-muted);color:var(--text-muted);font-size:11px}
.rfc-panel[hidden]{display:none}

/* Workspace */
.rfc-workspace{display:grid;grid-template-columns:230px minmax(0,1fr);gap:20px;align-items:start}
.rfc-nav{position:sticky;top:calc(var(--topbar-height) + 16px);display:grid;gap:2px;padding:10px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-elevated)}
.rfc-nav-title{padding:4px 8px 8px;color:var(--text-muted);font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.rfc-nav a{display:flex;align-items:center;gap:10px;padding:8px;border-radius:var(--radius-sm);color:var(--text-secondary);text-decoration:none}
.rfc-nav a:hover{background:var(--surface-hover);color:var(--heading)}
.rfc-nav a.is-current{background:var(--surface-hover);color:var(--interactive)}
.rfc-nav-num{display:inline-grid;place-items:center;flex:0 0 22px;height:22px;border-radius:50%;background:var(--surface-muted);font-size:11px;font-weight:800}
.rfc-nav a.is-current .rfc-nav-num{background:var(--interactive);color:#fff}
.rfc-nav-text{flex:1;min-width:0}
.rfc-nav-text strong{display:block;font-size:13px}
.rfc-nav-text small{display:block;color:var(--text-muted);font-size:11px;font-weight:500}
.rfc-nav-dot{flex:0 0 8px;height:8px;border-radius:50%;background:var(--warning);visibility:hidden}
.rfc-nav a.is-dirty .rfc-nav-dot{visibility:visible}

.rfc-editor{display:grid;gap:16px;min-width:0;margin:0}
.rfc-readonly-note{display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px dashed var(--border-strong);border-radius:var(--radius);color:var(--text-muted);font-size:13px}

/* Section cards */
.rfc-section{display:grid;gap:16px;scroll-margin-top:calc(var(--topbar-height) + 16px);transition:box-shadow var(--motion),border-color var(--motion)}
.rfc-section.is-dirty{border-color:var(--warning-border);box-shadow:inset 3px 0 0 var(--warning)}
.rfc-section-head{display:flex;align-items:flex-start;gap:12px}
.rfc-section-head>div{flex:1;min-width:0}
.rfc-section-head p{margin-top:3px;color:var(--text-muted);font-size:13px}
.rfc-section-num{display:inline-grid;place-items:center;flex:0 0 30px;height:30px;border-radius:8px;background:var(--info-bg);color:var(--info);font-size:13px;font-weight:800}

.rfc-grid{display:grid;gap:14px}
.rfc-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.rfc-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
.rfc-field{display:grid;gap:6px;min-width:0}
.rfc-field>span{display:flex;flex-wrap:wrap;align-items:baseline;gap:6px}
.rfc-field>span em{color:var(--text-soft);font-size:10px;font-style:normal;font-weight:600}
.rfc-field input,.rfc-field select{width:100%}
.rfc-field.has-error input{border-color:var(--danger);box-shadow:0 0 0 3px var(--danger-bg)}
.rfc-help{color:var(--text-muted);font-size:12px;font-weight:500}
.rfc-inline-error{color:var(--danger);font-size:12px;font-weight:600}
.rfc-inline-error[hidden]{display:none}
.rfc-muted{color:var(--text-soft)}
.rfc-pill{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;background:var(--surface-muted);color:var(--text-secondary);font-size:12px;font-weight:700;white-space:nowrap}

.rfc-title-sample{display:grid;gap:10px;padding:12px 14px;border:1px solid var(--row-border);border-radius:var(--radius);background:var(--surface-subtle)}
.rfc-title-sample-label{color:var(--text-muted);font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.rfc-title-sample dl{display:grid;gap:8px;margin:0}
.rfc-title-sample dl>div{display:grid;grid-template-columns:150px minmax(0,1fr);gap:10px;align-items:baseline}
.rfc-title-sample dt{color:var(--text-muted);font-size:12px;font-weight:600}
.rfc-title-sample dd{margin:0;color:var(--heading);font-size:14px;font-weight:700;overflow-wrap:anywhere}
.rfc-title-period{padding:1px 6px;border-radius:5px;background:var(--info-bg);color:var(--info);font-weight:600}
.rfc-legacy-note{padding:10px 12px;border:1px dashed var(--border-strong);border-radius:var(--radius-sm);color:var(--text-secondary);font-size:12px}
.rfc-legacy-note code{padding:1px 5px;border-radius:4px;background:var(--surface-muted);font-size:11px}
@media (max-width:700px){.rfc-title-sample dl>div{grid-template-columns:minmax(0,1fr);gap:2px}}

/* Switch */
.rfc-switch{display:inline-flex;align-items:center;gap:10px;width:max-content;max-width:100%;color:var(--text);font-size:13px;font-weight:600;cursor:pointer}
.rfc-switch input[type=checkbox]{position:absolute;opacity:0;width:1px;height:1px;min-height:0}
.rfc-switch-track{position:relative;flex:0 0 38px;height:22px;border-radius:999px;background:var(--border-strong);transition:background var(--motion)}
.rfc-switch-track::after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform var(--motion)}
.rfc-switch input:checked+.rfc-switch-track{background:var(--interactive)}
.rfc-switch input:checked+.rfc-switch-track::after{transform:translateX(16px)}
.rfc-switch input:focus-visible+.rfc-switch-track{box-shadow:var(--focus-ring)}
.rfc-switch input:disabled+.rfc-switch-track{opacity:.55}
.rfc-switch:has(input:disabled){cursor:not-allowed}
.rfc-switch.is-compact{gap:0}

/* Columns table */
.rfc-columns-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:var(--radius)}
.rfc-columns{width:100%;border-collapse:collapse;font-size:13px}
.rfc-columns th{padding:9px 10px;background:var(--table-heading-bg);color:var(--text-muted);font-size:10px;font-weight:800;letter-spacing:.06em;text-align:left;text-transform:uppercase}
.rfc-columns td{padding:7px 10px;border-top:1px solid var(--row-border);vertical-align:middle;background:var(--surface-elevated)}
.rfc-columns tr.is-grouped td{background:var(--surface-subtle)}
.rfc-columns tr.is-grouped td:first-child{box-shadow:inset 3px 0 0 var(--info-border)}
.rfc-columns tr.is-group-released td:first-child{box-shadow:inset 3px 0 0 var(--success-border)}
.rfc-columns tr.is-excluded .rfc-column-input{color:var(--text-soft);text-decoration:line-through}
.rfc-columns tr.is-drag-over td{box-shadow:inset 0 2px 0 var(--interactive)}
.rfc-columns tr.is-dragging td{opacity:.45}
.rfc-columns tr.is-moved td{animation:rfc-flash 700ms ease}
@keyframes rfc-flash{from{background:var(--surface-hover)}}
.rfc-col-move{width:92px}
.rfc-col-pos{width:34px;color:var(--text-muted);font-weight:700;text-align:center!important}
.rfc-col-group{width:110px}
.rfc-col-include{width:80px;text-align:center!important}
.rfc-column-input{width:100%;min-height:36px}
.rfc-move{display:flex;align-items:center;gap:2px}
.rfc-drag{display:inline-grid;place-items:center;width:24px;height:30px;color:var(--text-soft);font-size:16px;cursor:grab;user-select:none}
.rfc-drag:active{cursor:grabbing}
.rfc-arrow{display:inline-grid;place-items:center;width:28px;height:28px;padding:0;border:1px solid var(--border);border-radius:6px;background:var(--surface-elevated);color:var(--text-secondary);font-size:9px;cursor:pointer}
.rfc-arrow:hover:not(:disabled){border-color:var(--interactive);color:var(--interactive)}
.rfc-arrow:disabled{opacity:.35;cursor:default}
.rfc-group-chip{display:inline-flex;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.rfc-source-chip{display:inline-flex;padding:2px 8px;border-radius:999px;background:var(--surface-muted);color:var(--text-muted);font-size:11px;font-weight:700}
.rfc-source-chip.is-custom{background:var(--warning-bg);color:var(--warning)}
.rfc-combo{position:relative;flex:1 1 240px;min-width:0}
.rfc-combo input[type=text]{width:100%;height:36px}
.rfc-combo-list{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:20;margin:0;padding:4px;list-style:none;max-height:264px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface-elevated);box-shadow:0 10px 30px rgba(7,27,53,.14)}
.rfc-combo-list[hidden]{display:none}
.rfc-combo-option{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 9px;border-radius:6px;font-size:13px;cursor:pointer}
.rfc-combo-option>span:first-child{min-width:0;overflow-wrap:anywhere}
.rfc-combo-option .rfc-source-chip{flex:none;white-space:nowrap}
.rfc-combo-option.is-active{background:var(--surface-muted);box-shadow:inset 2px 0 0 var(--interactive)}
.rfc-combo-empty{padding:7px 9px;color:var(--text-muted);font-size:12px}
.rfc-group-chip.is-received{background:var(--info-bg);color:var(--info)}
.rfc-group-chip.is-released{background:var(--success-bg);color:var(--success)}

/* Protected formula */
.rfc-protected{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-subtle)}
.rfc-protected>div{display:grid;gap:4px}
.rfc-protected-label{color:var(--text-muted);font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.rfc-protected strong{color:var(--heading);font-size:13px}
.rfc-protected small{color:var(--text-muted);font-size:12px}

/* Signatories */
.rfc-signatory{display:grid;gap:12px;margin:0;padding:14px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-subtle);min-width:0}
.rfc-signatory legend{padding:0 6px;color:var(--heading);font-size:13px;font-weight:800}

/* Page setup choices */
.rfc-choice-group{margin:0;padding:0;border:0;min-width:0}
.rfc-choice-group legend{margin-bottom:8px;color:var(--text-secondary);font-size:12px;font-weight:700}
.rfc-choices{display:flex;flex-wrap:wrap;gap:8px}
.rfc-choice{position:relative;display:block;cursor:pointer}
.rfc-choice input{position:absolute;opacity:0;width:1px;height:1px;min-height:0}
.rfc-choice>span{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;min-width:104px;min-height:64px;padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-elevated);text-align:center;transition:border-color var(--motion),background var(--motion)}
.rfc-choice strong{color:var(--heading);font-size:13px}
.rfc-choice small{color:var(--text-muted);font-size:11px;font-weight:500}
.rfc-choice:hover>span{border-color:var(--border-strong)}
.rfc-choice input:checked+span{border-color:var(--interactive);background:var(--surface-hover);box-shadow:inset 0 0 0 1px var(--interactive)}
.rfc-choice input:focus-visible+span{box-shadow:var(--focus-ring)}
.rfc-choice input:disabled+span{cursor:not-allowed;opacity:.75}
.rfc-sheet-icon{display:block;border:2px solid currentColor;border-radius:2px;color:var(--text-muted)}
.rfc-sheet-icon.is-landscape{width:24px;height:17px}
.rfc-sheet-icon.is-portrait{width:17px;height:24px}
.rfc-choice input:checked+span .rfc-sheet-icon{color:var(--interactive)}

/* Save bar */
.rfc-savebar{position:sticky;bottom:12px;z-index:6;display:grid;grid-template-columns:minmax(180px,.8fr) minmax(220px,1.6fr) auto;align-items:end;gap:14px 18px;padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius-lg);background:var(--surface-elevated);box-shadow:0 10px 30px rgba(7,27,53,.14)}
.rfc-savebar.is-dirty{border-color:var(--warning-border)}
.rfc-savebar-state{display:flex;align-items:center;gap:10px;align-self:center}
.rfc-savebar-state strong{display:block;color:var(--heading);font-size:13px}
.rfc-savebar-state small{display:block;color:var(--text-muted);font-size:12px}
.rfc-state-dot{flex:0 0 10px;height:10px;border-radius:50%;background:var(--success)}
.rfc-savebar.is-dirty .rfc-state-dot{background:var(--warning);box-shadow:0 0 0 4px var(--warning-bg)}
.rfc-savebar-actions{display:flex;gap:8px}
.rfc-savebar-actions [hidden]{display:none}

/* Preview */
.rfc-preview-card,.rfc-history-card{display:grid;gap:16px}
.rfc-preview-head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:10px}
.rfc-preview-head p{margin-top:3px;color:var(--text-muted);font-size:13px}
.rfc-preview-scroll{overflow:auto;padding:24px;border-radius:var(--radius);background:var(--surface-muted)}
.rfc-paper{width:min(100%,1050px);margin:0 auto;padding:34px 38px;background:#fff;color:#111;box-shadow:0 6px 24px rgba(0,0,0,.14);font-family:"Times New Roman",Times,serif;font-size:12px;aspect-ratio:297/210}
.rfc-paper.is-portrait{width:min(100%,760px);aspect-ratio:210/297}
.rfc-paper.is-letter{aspect-ratio:11/8.5}.rfc-paper.is-letter.is-portrait{aspect-ratio:8.5/11}
.rfc-paper.is-legal{aspect-ratio:14/8.5}.rfc-paper.is-legal.is-portrait{aspect-ratio:8.5/14}
.rfc-paper-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
.rfc-paper-brand{display:flex;align-items:center;gap:12px}
.rfc-paper-brand small,.rfc-paper-brand strong{display:block}
.rfc-paper-brand strong{font-size:14px}
.rfc-paper-logo{width:56px;height:56px;object-fit:contain}
.rfc-paper-logo[hidden]{display:none}
.rfc-paper-code{font-size:12px}
.rfc-paper-rule{height:2px;margin:10px 0 14px;background:#111}
.rfc-paper-title{margin:0 0 12px;color:#111;font-size:14px;text-align:center}
.rfc-paper-table{width:100%;border-collapse:collapse;font-size:10.5px}
.rfc-paper-table th,.rfc-paper-table td{padding:5px 6px;border:1px solid #333;color:#111;text-align:center;vertical-align:middle}
.rfc-paper-table th{background:#f2f2f2;font-weight:700}
.rfc-paper-table td{overflow-wrap:anywhere}
.rfc-paper-formula{margin-top:14px!important;font-size:11px}
.rfc-paper-formula[hidden]{display:none}
.rfc-paper-signatures{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:40px;margin-top:28px}
.rfc-paper-signatures>div{display:grid;gap:2px}
.rfc-paper-signatures [hidden]{display:none}
.rfc-paper-ink{height:28px;border-bottom:1px solid #111;max-width:260px}
.rfc-paper-signatures strong{text-transform:uppercase}
.rfc-paper-footer{display:flex;justify-content:space-between;gap:12px;margin-top:28px;padding-top:6px;border-top:1px solid #999;font-size:10px}

/* History */
.rfc-history-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:var(--radius)}
.rfc-history{width:100%;border-collapse:collapse;font-size:13px}
.rfc-history th{padding:9px 12px;background:var(--table-heading-bg);color:var(--text-muted);font-size:10px;font-weight:800;letter-spacing:.06em;text-align:left;text-transform:uppercase;white-space:nowrap}
.rfc-history td{padding:10px 12px;border-top:1px solid var(--row-border);vertical-align:top}
.rfc-history tr.is-selected td{background:var(--surface-hover)}
.rfc-history-sub{display:block;margin-top:3px;color:var(--text-muted);font-size:11px}
.rfc-history-action{text-align:right;white-space:nowrap}
.rfc-empty{text-align:center}

/* Responsive */
@media (max-width:1180px){
    .rfc-workspace{grid-template-columns:minmax(0,1fr)}
    .rfc-nav{display:none}
}
@media (max-width:900px){
    .rfc-meta{grid-template-columns:repeat(2,minmax(0,1fr))}
    .rfc-grid-3{grid-template-columns:minmax(0,1fr)}
    .rfc-savebar{grid-template-columns:minmax(0,1fr)}
    .rfc-savebar-actions{justify-content:flex-end}
}
@media (max-width:700px){
    .rfc-grid-2{grid-template-columns:minmax(0,1fr)}
    .rfc-col-group{display:none}
    .rfc-review-actions{justify-content:stretch}
    .rfc-review-actions>*{flex:1 1 auto}
    .rfc-review-actions .button{width:100%}
}
/* The global button rule paints every <button> as a secondary button. */
.rfc .button.primary{color:#fff!important;background:var(--primary-action)!important;border-color:var(--primary-action)!important}
.rfc .button.primary:hover:not(:disabled){background:var(--primary-action-hover)!important;border-color:var(--primary-action-hover)!important}
.rfc .button.primary:disabled{opacity:.5;cursor:not-allowed;transform:none}
.rfc .rfc-tab{min-height:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;color:var(--text-muted)!important;transform:none!important}
.rfc .rfc-tab:hover{color:var(--heading)!important}
.rfc .rfc-tab.is-active{color:var(--interactive)!important}
.rfc .rfc-tab:focus-visible{box-shadow:var(--focus-ring)!important}
.rfc .rfc-arrow{min-height:0}
.rfc-section,.rfc-editor{grid-template-columns:minmax(0,1fr)}
.rfc-switch{width:auto}
.rfc-protected strong{overflow-wrap:anywhere}

@media (prefers-reduced-motion:reduce){
    .rfc *{transition:none!important;animation:none!important}
}

/* Smart Form Editor: compact, nonoverlapping configuration workspace */
.rfc-workspace{grid-template-columns:minmax(0,1.12fr) minmax(335px,.88fr);gap:14px;align-items:start}
.rfc-nav{grid-column:1/-1;position:static;display:flex;flex-wrap:wrap;align-items:center;gap:5px;padding:8px}
.rfc-nav-title{margin:0 8px 0 4px;padding:0}
.rfc-nav a{padding:7px 9px;gap:7px}
.rfc-nav-text small{display:none}
.rfc-editor{grid-column:1;grid-row:2;min-width:0}
.rfc-live{grid-column:2;grid-row:2;position:sticky;top:calc(var(--topbar-height, 64px) + 12px);min-width:0;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-elevated);overflow:hidden}
.rfc-live-head{padding:12px 13px;display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:1px solid var(--row-border)}
.rfc-live-head strong{display:block;color:var(--heading);font-size:13px}
.rfc-live-head small{display:block;color:var(--text-muted);font-size:11px}
.rfc-live-content{padding:10px;max-height:calc(100vh - 230px);overflow:auto;background:var(--surface-muted)}
.rfc-live-content .rfc-paper{width:900px;max-width:none;margin:0;zoom:.42;padding:28px 36px;box-shadow:0 3px 14px rgba(0,0,0,.12)}
.rfc-live-note{padding:9px 12px;color:var(--text-muted);font-size:11px}
.rfc-layout-warning{margin:0;padding:10px 12px;border:1px solid var(--warning-border);border-radius:var(--radius-sm);background:var(--warning-bg);color:var(--warning);font-size:12px}
.rfc-layout-warning[hidden]{display:none}
.rfc-section{gap:12px;padding:16px}
.rfc-section-head p{font-size:12px}
.rfc-grid{gap:10px}
.rfc-savebar{position:static;bottom:auto;box-shadow:none;z-index:auto;padding:12px 16px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px 16px;border-radius:var(--radius)}
.rfc-savebar[hidden]{display:none}
.rfc-savebar-state{min-width:0;flex:1 1 220px}
.rfc-savebar-actions{flex-wrap:wrap;justify-content:flex-end;margin-left:auto}
.rfc-savebar-state small{font-size:11px}
.rfc-column-included-input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.rfc-columns tr.is-excluded{display:none}
.rfc-add-column{display:flex;flex-wrap:wrap;align-items:center;gap:8px;background:var(--surface-subtle);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px}
.rfc-add-column label{font-size:12px;font-weight:700;color:var(--text-secondary)}
.rfc-add-column select{flex:1 1 165px;min-width:0;max-width:100%;height:36px}
.rfc-add-column button{white-space:nowrap;min-height:36px}
.rfc-remove-col{padding:5px 9px!important;min-height:30px!important;border:1px solid var(--danger-border)!important;border-radius:6px!important;background:transparent!important;color:var(--danger)!important;font-size:11px!important;font-weight:700;cursor:pointer}
.rfc-remove-col:hover:not(:disabled){background:var(--danger-bg)!important}
.rfc-remove-col:disabled{opacity:.45;cursor:not-allowed}
.rfc-col-include{width:95px}
.rfc-readonly-note{font-size:12px}
@media(max-width:1220px){.rfc-workspace{grid-template-columns:minmax(0,1fr) minmax(300px,.87fr)}.rfc-live-content .rfc-paper{zoom:.35}}
@media(max-width:1080px){.rfc-workspace{grid-template-columns:minmax(0,1fr)}.rfc-nav{grid-column:1;display:flex}.rfc-editor{grid-column:1;grid-row:2}.rfc-live{display:none}}
@media(max-width:700px){.rfc-nav a{flex:1 1 120px}.rfc-overview .rfc-meta{grid-template-columns:repeat(2,minmax(0,1fr))}.rfc-savebar{grid-template-columns:minmax(0,1fr)}}


/* SPMU_LIVE_ZOOM_V1: centered, scrollable paper preview and persistent zoom toolbar. */
.rfc-live-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:5px;padding:8px;background:#293442;color:#fff}
.rfc .rfc-live-zoom-button,.rfc .rfc-live-zoom-fit{display:inline-flex;align-items:center;justify-content:center;border:1px solid transparent!important;border-radius:6px!important;background:transparent!important;color:#f7f9fc!important;box-shadow:none!important;transform:none!important;cursor:pointer;font-size:12px!important;font-weight:650!important;min-height:30px!important;padding:5px 8px!important}
.rfc .rfc-live-zoom-button{min-width:32px;font-size:19px!important;line-height:1!important}
.rfc .rfc-live-zoom-fit:hover,.rfc .rfc-live-zoom-button:hover{background:#3a4c60!important;color:#fff!important}
.rfc .rfc-live-zoom-fit[aria-pressed="true"]{background:#48617b!important;border-color:#647e99!important}
.rfc .rfc-live-zoom-button:focus-visible,.rfc .rfc-live-zoom-fit:focus-visible{outline:2px solid #a6d0ff!important;outline-offset:2px!important}
.rfc-live-zoom-label{display:inline-block;min-width:50px;text-align:center;font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
.rfc-live-toolbar-divider{height:20px;width:1px;background:#64748b;margin:0 4px}
.rfc-live-content{height:clamp(330px,60vh,630px);max-height:none!important;overflow:auto!important;overscroll-behavior:contain;padding:0!important;background:#e5eaf0!important;scrollbar-width:thin;scrollbar-color:#a7b3c2 transparent}
.rfc-live-stage{display:flex;justify-content:center;align-items:flex-start;box-sizing:border-box;margin:0;position:relative}
.rfc-live-page-frame{position:relative;flex:0 0 auto;box-sizing:border-box}
.rfc-live-content .rfc-paper{position:absolute!important;top:0;left:0;width:var(--rfc-live-paper-width,940px)!important;max-width:none!important;margin:0!important;zoom:1!important;padding:28px 36px!important;box-sizing:border-box;transform-origin:top left!important;box-shadow:0 4px 16px rgba(14,30,49,.18)!important}
.rfc-live-content:focus-visible{outline:2px solid var(--interactive);outline-offset:-2px}
@media(max-width:700px){.rfc-live-toolbar{justify-content:flex-start}.rfc-live-content{height:440px}}


/* SPMU-RFC-DOCUMENT-ALIGN-V1: compact, aligned document fields */
#rfc-section-document {
    gap: 12px;
    padding: 18px 18px 20px;
}
#rfc-section-document .rfc-section-head {
    align-items: center;
    margin-bottom: 2px;
}
#rfc-section-document .rfc-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: start;
    column-gap: 12px;
    row-gap: 12px;
}
#rfc-section-document .rfc-field {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 7px;
    min-width: 0;
    margin: 0;
}
#rfc-section-document .rfc-field > span {
    display: block;
    min-height: 17px;
    margin: 0;
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 700;
    line-height: 17px;
    white-space: nowrap;
}
#rfc-section-document .rfc-field > input {
    display: block;
    width: 100%;
    min-width: 0;
    height: 42px;
    min-height: 42px;
    box-sizing: border-box;
    margin: 0;
    padding: 0 12px;
    border-radius: 8px;
    font-size: 13px;
    line-height: normal;
}
#rfc-section-document .rfc-field > input[type="date"] {
    padding-right: 10px;
}
@media (max-width: 760px) {
    #rfc-section-document .rfc-grid-3 {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>


<script>
/* REPORT FORM CONFIGURATION (rfc) */
(() => {
    'use strict';

    const root = document.querySelector('[data-rfc]');
    if (!root) return;

    const editor = root.querySelector('[data-rfc-editor]');
    const readonly = !editor || editor.dataset.readonly === '1';
    const IGNORED_FIELDS = new Set(['_token', '_action_token']);

    let dirty = false;
    let submitting = false;

    /* ---------------------------------------------------------------
     * Tabs
     * ------------------------------------------------------------- */
    const tabs = [...root.querySelectorAll('[data-rfc-tab]')];
    const panels = [...root.querySelectorAll('[data-rfc-panel]')];

    function showTab(name) {
        tabs.forEach((tab) => {
            const active = tab.dataset.rfcTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.rfcPanel !== name;
        });
        if (name === 'preview') renderPreview();
    }

    tabs.forEach((tab) => tab.addEventListener('click', () => showTab(tab.dataset.rfcTab)));

    root.querySelectorAll('[data-rfc-show-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            showTab(button.dataset.rfcShowTab);
            root.querySelector('.rfc-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    /* ---------------------------------------------------------------
     * Confirmed actions (activate / discard)
     *
     * Confirmation runs on click, before the form's submit event, so a
     * cancelled dialog never trips the global duplicate-submit lock.
     * ------------------------------------------------------------- */
    root.querySelectorAll('[data-rfc-confirm]').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (button.hasAttribute('data-rfc-requires-saved') && dirty) {
                // Unreachable in normal use: Review & Activate is hidden while
                // changes are unsaved. Never activate over them; show the save card.
                event.preventDefault();
                refreshState();
                return;
            }
            if (!window.confirm(button.dataset.rfcConfirm)) {
                event.preventDefault();
                return;
            }
            submitting = true;
        });
    });

    /* ---------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------- */
    const field = (name) => editor?.querySelector(`[data-preview="${name}"]`);
    const val = (name, fallback = '') => {
        const el = field(name);
        if (!el) return fallback;
        if (el.type === 'radio') {
            return editor.querySelector(`[data-preview="${name}"]:checked`)?.value ?? fallback;
        }
        return el.value;
    };
    const isOn = (name, fallback = true) => {
        const el = field(name);
        return el ? el.checked : fallback;
    };
    const fullPreview = root.querySelector('[data-rfc-panel="preview"]');
    const out = (name) => [...(fullPreview?.querySelectorAll(`[data-out="${name}"]`) ?? [])];
    const FIXED_HEADER = @json($header);
    const setOut = (name, text) => out(name).forEach((el) => { el.textContent = text ?? ''; });

    const FIXED_REPORT_TITLE = @json($reportTitle);
    const SAMPLE_PHRASE = @json($titleSamples['month']['phrase']);

    const columnRows = () => [...(editor?.querySelectorAll('[data-column-row]') ?? [])];

    /* ---------------------------------------------------------------
     * Preview
     * ------------------------------------------------------------- */
    const SAMPLES = [
        {
            control_no: 'REQ-2025-0076', requesting_office: 'CEA', requested_equipment: 'Monobloc chair (10)',
            dates_needed: '18 Nov 2025', received_date: '17 Nov 2025', received_time: '8:35 AM',
            released_date: '18 Nov 2025', released_time: '8:40 AM', total_time_allocated: '24 h 5 min', remarks: 'Completed',
            borrower_name: 'Sample Borrower A', requested_quantity: '10', approved_date: '18 Nov 2025',
            purpose_event: 'College of Engineering and Architecture General Assembly', location: 'CEA Covered Court, Main Campus',
            division: 'Academic', off_campus: 'No', approved_by: 'Sample SPMU Head', custody_no: 'CUS-2025-0058',
        },
        {
            control_no: 'REQ-2025-0081', requesting_office: 'CCS', requested_equipment: 'LCD projector (1)',
            dates_needed: '20 Nov 2025', received_date: '19 Nov 2025', received_time: '1:10 PM',
            released_date: '—', released_time: '—', total_time_allocated: '—', remarks: 'Under SPMU Review',
            borrower_name: 'Sample Borrower B', requested_quantity: '1', approved_date: '—',
            purpose_event: 'Capstone project defense', location: 'Naga City Convention Center',
            division: 'Academic', off_campus: 'Yes', approved_by: '—', custody_no: '—',
        },
    ];

    function renderTable() {
        const head = out('table_head')[0];
        const body = out('table_body')[0];
        if (!head || !body) return;

        const columns = columnRows()
            .map((row) => ({
                key: row.dataset.columnKey,
                group: row.dataset.group || null,
                label: row.querySelector('[data-column-label]')?.value ?? '',
                visible: row.querySelector('[data-column-visible]')?.checked ?? false,
            }))
            .filter((column) => column.visible);

        const grouped = columns.some((column) => column.group);
        const top = document.createElement('tr');
        const bottom = document.createElement('tr');

        columns.forEach((column, index) => {
            if (!column.group) {
                const th = document.createElement('th');
                th.textContent = column.label;
                if (grouped) th.rowSpan = 2;
                top.appendChild(th);
                return;
            }
            if (index === 0 || columns[index - 1].group !== column.group) {
                let span = 1;
                while (columns[index + span]?.group === column.group) span++;
                const th = document.createElement('th');
                th.colSpan = span;
                th.textContent = column.group;
                top.appendChild(th);
            }
            const child = document.createElement('th');
            child.textContent = column.label;
            bottom.appendChild(child);
        });

        head.replaceChildren(top);
        if (grouped && bottom.children.length) head.appendChild(bottom);

        body.replaceChildren(...SAMPLES.map((sample) => {
            const tr = document.createElement('tr');
            columns.forEach((column) => {
                const td = document.createElement('td');
                td.textContent = sample[column.key] ?? '';
                tr.appendChild(td);
            });
            return tr;
        }));
    }

    function renderPreview() {
        if (!editor) return;

        // Fixed official form title; reporting period comes from the chosen scope.
        setOut('title', (FIXED_REPORT_TITLE + ' ' + SAMPLE_PHRASE).trim());

        ['form_code', 'revision_label', 'prepared_by_name', 'prepared_by_designation',
            'noted_by_name', 'noted_by_designation']
            .forEach((name) => setOut(name, val(name)));
        setOut('header_republic', FIXED_HEADER.republic || 'Republic of the Philippines');
        setOut('header_institution', FIXED_HEADER.institution || 'CAMARINES SUR POLYTECHNIC COLLEGES');
        setOut('header_location', FIXED_HEADER.location || 'Nabua, Camarines Sur');
        setOut('prepared_by_label', (val('prepared_by_label') || 'Prepared by') + ':');
        setOut('noted_by_label', (val('noted_by_label') || 'Noted by') + ':');

        out('logo').forEach((el) => { el.hidden = false; });
        out('formula').forEach((el) => { el.hidden = !isOn('formula_enabled'); });
        out('signature_date').forEach((el) => { el.hidden = !isOn('show_signature_date'); });

        const date = val('effectivity_date');
        setOut('effectivity', date
            ? new Date(date + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
            : '—');

        const size = val('page_size', 'A4');
        const orientation = val('orientation', 'LANDSCAPE');
        const paper = root.querySelector('[data-rfc-paper]');
        if (paper) {
            paper.classList.toggle('is-portrait', orientation === 'PORTRAIT');
            paper.classList.toggle('is-letter', size === 'LETTER');
            paper.classList.toggle('is-legal', size === 'LEGAL');
        }
        const sizeLabel = { A4: 'A4', LETTER: 'Letter', LEGAL: 'Legal' }[size] ?? size;
        root.querySelectorAll('[data-rfc-paper-meta]').forEach((el) => {
            el.textContent = sizeLabel + ' · ' + (orientation === 'PORTRAIT' ? 'Portrait' : 'Landscape');
        });

        renderTable();
        const live = root.querySelector('[data-rfc-live-content]');
        const source = fullPreview?.querySelector('[data-rfc-paper]');
        if (live && source) {
            const copy = source.cloneNode(true);
            copy.removeAttribute('data-rfc-paper');
            copy.removeAttribute('id');
            copy.setAttribute('aria-label', 'Unsaved official form sample');
            live.replaceChildren(copy);
        }
    }

    /* ---------------------------------------------------------------
     * Column count + ordering
     * ------------------------------------------------------------- */
    const tbody = editor?.querySelector('[data-rfc-columns]');
    const columnError = editor?.querySelector('[data-rfc-column-error]');

    function refreshColumns() {
        const rows = columnRows();
        const units = columnUnits();

        rows.forEach((row, index) => {
            const order = row.querySelector('[data-column-order]');
            if (order) order.value = String(index + 1);
            const position = row.querySelector('[data-column-position]');
            if (position) position.textContent = String(index + 1);
            row.classList.toggle('is-excluded', !row.querySelector('[data-column-visible]')?.checked);
        });

        units.forEach((unit, index) => {
            unit.forEach((row) => {
                const up = row.querySelector('[data-rfc-move="-1"]');
                const down = row.querySelector('[data-rfc-move="1"]');
                if (up) up.disabled = index === 0;
                if (down) down.disabled = index === units.length - 1;
            });
        });

        const included = rows.filter((row) => row.querySelector('[data-column-visible]')?.checked).length;
        const count = root.querySelector('[data-rfc-column-count]');
        if (count) {
            const system = rows.filter((row) => row.dataset.custom !== '1');
            const systemIncluded = system.filter((row) => row.querySelector('[data-column-visible]')?.checked).length;
            const custom = rows.length - system.length;
            count.textContent = `${systemIncluded} of ${system.length} system fields`
                + (custom ? ` + ${custom} custom` : '');
        }
        const warning = root.querySelector('[data-rfc-layout-warning]');
        if (warning) {
            // Advisory only: the official layout has 10 columns and every extra one narrows the rest.
            const portrait = val('orientation', 'LANDSCAPE') === 'PORTRAIT';
            warning.hidden = included <= 10;
            warning.textContent = `This form includes ${included} columns; the official layout has 10. `
                + 'Each added column narrows the others, so long text wraps onto more lines. '
                + (portrait ? 'Portrait pages are especially tight; consider Landscape. ' : '')
                + 'Check Full Preview and the printed/PDF output before activating.';
        }

        // The Add Column list offers only what is not on the form right now.
        if (addOptions && !addOptions.hidden) renderAddOptions();
    }

    // System fields not currently included, one entry per field or grouped pair.
    function availableSystemFields() {
        const fields = [];
        const seen = new Set();
        columnRows().forEach((row) => {
            if (row.dataset.custom === '1' || row.querySelector('[data-column-visible]')?.checked) return;
            const id = row.dataset.group || row.dataset.columnKey;
            if (seen.has(id)) return;
            seen.add(id);
            fields.push({
                id,
                label: row.dataset.group
                    ? `${row.dataset.group.charAt(0)}${row.dataset.group.slice(1).toLowerCase()} (Date & Time)`
                    : row.querySelector('[data-column-label]')?.value || id,
            });
        });
        return fields;
    }

    // A grouped pair (Received / Released) is one movable unit.
    function columnUnits() {
        const units = [];
        const seen = new Map();
        columnRows().forEach((row) => {
            const key = row.dataset.group || row.dataset.columnKey;
            if (!seen.has(key)) {
                seen.set(key, []);
                units.push(seen.get(key));
            }
            seen.get(key).push(row);
        });
        return units;
    }

    function placeUnit(unit, beforeNode) {
        const fragment = document.createDocumentFragment();
        unit.forEach((row) => fragment.appendChild(row));
        tbody.insertBefore(fragment, beforeNode);
        unit.forEach((row) => {
            row.classList.remove('is-moved');
            void row.offsetWidth;
            row.classList.add('is-moved');
        });
    }

    function afterReorder() {
        refreshColumns();
        renderPreview();
        refreshState();
    }

    /* ---------------------------------------------------------------
     * Add Column: one searchable control for both kinds of column.
     *
     * A system field is added only when its own option is chosen; text
     * that merely matches a field name is never linked to system data.
     * A custom column is a heading with no system data behind it. Its key
     * is generated once here and never derived from the heading, so
     * renaming a column cannot change what it is.
     * ------------------------------------------------------------- */
    const addSearch = root.querySelector('[data-rfc-add-search]');
    const addOptions = root.querySelector('[data-rfc-add-options]');
    const customError = root.querySelector('[data-rfc-custom-error]');
    const customTemplate = root.querySelector('[data-rfc-custom-template]');
    const MAX_CUSTOM_COLUMNS = @json($maxCustomColumns);
    const customHeading = () => (addSearch?.value ?? '').replace(/\s+/g, ' ').trim();
    let addActive = -1;

    const addOptionNodes = () => [...(addOptions?.querySelectorAll('[role="option"]') ?? [])];

    function setAddActive(index) {
        const options = addOptionNodes();
        addActive = options.length ? Math.max(-1, Math.min(index, options.length - 1)) : -1;
        options.forEach((option, i) => {
            option.classList.toggle('is-active', i === addActive);
            option.setAttribute('aria-selected', i === addActive ? 'true' : 'false');
        });
        if (addActive >= 0) {
            addSearch.setAttribute('aria-activedescendant', options[addActive].id);
            options[addActive].scrollIntoView({ block: 'nearest' });
        } else {
            addSearch?.removeAttribute('aria-activedescendant');
        }
    }

    function renderAddOptions() {
        if (!addOptions || !addSearch) return;
        const heading = customHeading();
        const needle = heading.toLowerCase();
        const fields = availableSystemFields()
            .filter((field) => !needle || field.label.toLowerCase().includes(needle));
        const nodes = [];

        const option = (kind, value, text, tag) => {
            const li = document.createElement('li');
            li.className = 'rfc-combo-option';
            li.id = `rfc-add-option-${nodes.length}`;
            li.setAttribute('role', 'option');
            li.dataset.kind = kind;
            li.dataset.value = value;
            const name = document.createElement('span');
            name.textContent = text;
            const chip = document.createElement('span');
            chip.className = 'rfc-source-chip' + (kind === 'custom' ? ' is-custom' : '');
            chip.textContent = tag;
            li.append(name, chip);
            nodes.push(li);
        };

        fields.forEach((field) => option('system', field.id, field.label, 'System Field'));
        if (heading) option('custom', heading, `Create “${heading}”`, 'Custom Column');

        if (!nodes.length) {
            const note = document.createElement('li');
            note.className = 'rfc-combo-empty';
            note.setAttribute('role', 'presentation');
            note.textContent = 'Every system field is already included. Type a heading to create a custom column.';
            nodes.push(note);
        }

        addOptions.replaceChildren(...nodes);
        addOptions.hidden = false;
        addSearch.setAttribute('aria-expanded', 'true');
        // Nothing is highlighted until the user moves to an option.
        setAddActive(-1);
    }

    function closeAddOptions() {
        if (!addOptions || !addSearch) return;
        addOptions.hidden = true;
        addOptions.replaceChildren();
        addSearch.setAttribute('aria-expanded', 'false');
        setAddActive(-1);
    }

    function focusHeading(row) {
        const label = row?.querySelector('[data-column-label]');
        if (!label) return;
        row.scrollIntoView({ block: 'nearest' });
        label.focus();
        label.select();
    }

    function addSystemField(id) {
        const rows = columnRows().filter((row) => row.dataset.custom !== '1'
            && (row.dataset.group || row.dataset.columnKey) === id);
        // Already on the form, or not a field this form knows: nothing to add.
        if (!rows.length || rows.some((row) => row.querySelector('[data-column-visible]')?.checked)) return false;
        rows.forEach((row) => { row.querySelector('[data-column-visible]').checked = true; });
        afterReorder();
        focusHeading(rows[0]);
        return true;
    }

    function chooseAddOption(option) {
        if (!option) return;
        const added = option.dataset.kind === 'system'
            ? addSystemField(option.dataset.value)
            : addCustomColumn();
        if (!added) return;
        addSearch.value = '';
        showCustomError('');
        closeAddOptions();
    }

    addSearch?.addEventListener('focus', renderAddOptions);
    addSearch?.addEventListener('input', () => {
        showCustomError('');
        renderAddOptions();
    });
    addSearch?.addEventListener('blur', closeAddOptions);
    addSearch?.addEventListener('keydown', (event) => {
        const options = addOptionNodes();
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (addOptions.hidden) {
                renderAddOptions();
                return;
            }
            if (!options.length) return;
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setAddActive(addActive < 0
                ? (step === 1 ? 0 : options.length - 1)
                : (addActive + step + options.length) % options.length);
        } else if (event.key === 'Enter') {
            // Never submits the configuration form. With nothing highlighted,
            // Enter only moves to the first option so the choice stays explicit.
            event.preventDefault();
            if (addOptions.hidden) renderAddOptions();
            else if (addActive >= 0) chooseAddOption(options[addActive]);
            else setAddActive(0);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            if (!addOptions.hidden) closeAddOptions();
            else addSearch.value = '';
        }
    });
    // mousedown keeps focus in the input, so the list is still open for the click.
    addOptions?.addEventListener('mousedown', (event) => event.preventDefault());
    addOptions?.addEventListener('mousemove', (event) => {
        const index = addOptionNodes().indexOf(event.target.closest('[role="option"]'));
        if (index >= 0 && index !== addActive) setAddActive(index);
    });
    addOptions?.addEventListener('click', (event) => chooseAddOption(event.target.closest('[role="option"]')));

    function customKey() {
        const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        const taken = new Set(columnRows().map((row) => row.dataset.columnKey));
        let key;
        do {
            const bytes = new Uint8Array(10);
            (window.crypto || window.msCrypto).getRandomValues(bytes);
            key = 'custom_' + [...bytes].map((byte) => alphabet[byte % alphabet.length]).join('');
        } while (taken.has(key));
        return key;
    }

    function showCustomError(message) {
        if (!customError) return;
        customError.textContent = message;
        customError.hidden = !message;
    }

    function addCustomColumn() {
        const heading = customHeading();
        const source = customTemplate?.content.querySelector('[data-column-row]');
        if (!tbody || !source) return false;
        if (!heading) {
            showCustomError('Enter a heading for the custom column.');
            return false;
        }
        if (columnRows().filter((row) => row.dataset.custom === '1').length >= MAX_CUSTOM_COLUMNS) {
            showCustomError(`A form may have at most ${MAX_CUSTOM_COLUMNS} custom columns.`);
            return false;
        }

        const key = customKey();
        const row = source.cloneNode(true);
        row.dataset.columnKey = key;
        row.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace('__custom__', key); });
        const label = row.querySelector('[data-column-label]');
        label.value = heading;
        label.setAttribute('aria-label', 'Heading for custom column');
        row.querySelector('[data-rfc-move="-1"]')?.setAttribute('aria-label', 'Move custom column up');
        row.querySelector('[data-rfc-move="1"]')?.setAttribute('aria-label', 'Move custom column down');
        row.querySelector('[data-rfc-remove-column]')?.setAttribute('aria-label', 'Remove custom column');

        tbody.appendChild(row);
        afterReorder();
        focusHeading(row);
        return true;
    }

    if (tbody && !readonly) {
        tbody.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-rfc-remove-column]');
            if (remove && !remove.disabled) {
                const row = remove.closest('[data-column-row]');
                // A custom column has nothing to return to in the catalogue: removing it deletes it.
                if (row.dataset.custom === '1') {
                    row.remove();
                    showCustomError('');
                    afterReorder();
                    return;
                }
                const key = row.dataset.group || row.dataset.columnKey;
                const included = columnRows().filter(r => r.querySelector('[data-column-visible]')?.checked);
                const matches = included.filter(r => (r.dataset.group || r.dataset.columnKey) === key);
                if (key === 'control_no' || included.length <= matches.length) return;
                matches.forEach(r => { r.querySelector('[data-column-visible]').checked = false; });
                afterReorder();
                return;
            }
            const button = event.target.closest('[data-rfc-move]');
            if (!button || button.disabled) return;

            const row = button.closest('[data-column-row]');
            const units = columnUnits();
            const index = units.findIndex((unit) => unit.includes(row));
            const target = index + Number(button.dataset.rfcMove);
            if (index < 0 || target < 0 || target >= units.length) return;

            if (target < index) {
                placeUnit(units[index], units[target][0]);
            } else {
                placeUnit(units[index], units[target][units[target].length - 1].nextSibling);
            }
            afterReorder();
            if (!button.disabled) button.focus();
            else row.querySelector(`[data-rfc-move="${-Number(button.dataset.rfcMove)}"]`)?.focus();
        });

        let dragged = null;

        tbody.addEventListener('dragstart', (event) => {
            const handle = event.target.closest('.rfc-drag');
            if (!handle) return;
            const row = handle.closest('[data-column-row]');
            dragged = columnUnits().find((unit) => unit.includes(row)) ?? null;
            dragged?.forEach((r) => r.classList.add('is-dragging'));
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.columnKey);
            event.dataTransfer.setDragImage(row, 20, 20);
        });

        tbody.addEventListener('dragover', (event) => {
            if (!dragged) return;
            const row = event.target.closest('[data-column-row]');
            if (!row) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            columnRows().forEach((r) => r.classList.toggle('is-drag-over', r === row));
        });

        tbody.addEventListener('drop', (event) => {
            if (!dragged) return;
            event.preventDefault();
            const row = event.target.closest('[data-column-row]');
            const targetUnit = row ? columnUnits().find((unit) => unit.includes(row)) : null;
            if (targetUnit && targetUnit !== dragged && !dragged.includes(row)) {
                const rect = row.getBoundingClientRect();
                const after = event.clientY > rect.top + rect.height / 2;
                placeUnit(dragged, after ? targetUnit[targetUnit.length - 1].nextSibling : targetUnit[0]);
                afterReorder();
            }
        });

        tbody.addEventListener('dragend', () => {
            columnRows().forEach((r) => r.classList.remove('is-drag-over', 'is-dragging'));
            dragged = null;
        });

        // Never allow the last included column to be switched off.
        tbody.addEventListener('change', (event) => {
            if (!event.target.matches('[data-column-visible]')) return;
            const included = columnRows().filter((row) => row.querySelector('[data-column-visible]')?.checked);
            if (included.length === 0) {
                event.target.checked = true;
                if (columnError) columnError.hidden = false;
            } else if (columnError) {
                columnError.hidden = true;
            }
        });
    }

    /* ---------------------------------------------------------------
     * Section navigation highlight
     * ------------------------------------------------------------- */
    const navLinks = [...root.querySelectorAll('[data-rfc-nav]')];
    if ('IntersectionObserver' in window && navLinks.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const id = entry.target.dataset.rfcSection;
                navLinks.forEach((link) => link.classList.toggle('is-current', link.dataset.rfcNav === id));
            });
        }, { rootMargin: '-30% 0px -60% 0px' });
        root.querySelectorAll('[data-rfc-section]').forEach((section) => observer.observe(section));
    }

    renderPreview();
    refreshColumns();

    if (readonly) return;

    /* ---------------------------------------------------------------
     * Change tracking + saving
     * ------------------------------------------------------------- */
    const savebar = editor.querySelector('[data-rfc-savebar]');
    const saveButton = editor.querySelector('[data-rfc-save]');
    const resetButton = editor.querySelector('[data-rfc-reset]');
    const stateLabel = editor.querySelector('[data-rfc-state]');
    const stateDetail = editor.querySelector('[data-rfc-state-detail]');
    const reviewCard = root.querySelector('[data-rfc-review]');
    const activateButton = root.querySelector('[data-rfc-requires-saved]');
    const activationReady = activateButton?.dataset.rfcReady === '1';
    const hasServerErrors = editor.dataset.hasErrors === '1';
    const defaultDetail = stateDetail?.textContent.trim() ?? '';

    function snapshot() {
        const values = new Map();
        for (const [name, value] of new FormData(editor)) {
            if (IGNORED_FIELDS.has(name)) continue;
            values.set(name, values.has(name) ? values.get(name) + '\u0001' + value : String(value));
        }
        return values;
    }

    const baseline = snapshot();

    function changedSections() {
        const now = snapshot();
        const sections = new Set();
        new Set([...baseline.keys(), ...now.keys()]).forEach((name) => {
            if (baseline.get(name) === now.get(name)) return;
            const input = editor.querySelector(`[name="${CSS.escape(name)}"]`);
            // A removed custom column leaves no input behind; it is still a Table Columns change.
            const section = input?.closest('[data-rfc-section]')
                ?? (name.startsWith('column_') ? root.querySelector('[data-rfc-section="columns"]') : null);
            if (section) sections.add(section);
        });
        return [...sections];
    }

    function refreshState() {
        const sections = changedSections();
        dirty = hasServerErrors || sections.length > 0;

        root.querySelectorAll('[data-rfc-section]').forEach((section) => {
            const changed = sections.includes(section);
            section.classList.toggle('is-dirty', changed);
            navLinks
                .find((link) => link.dataset.rfcNav === section.dataset.rfcSection)
                ?.classList.toggle('is-dirty', changed);
        });

        savebar?.classList.toggle('is-dirty', dirty);
        if (saveButton && !submitting) saveButton.disabled = !dirty;
        if (resetButton) resetButton.hidden = !dirty;
        if (activateButton) activateButton.disabled = dirty || !activationReady;

        // On a working Draft the two cards are one workflow: save while there
        // are unsaved changes, review and activate once everything is saved.
        if (reviewCard) {
            reviewCard.hidden = dirty;
            if (savebar) savebar.hidden = !dirty;
        }

        if (stateLabel) {
            stateLabel.textContent = dirty ? 'Unsaved changes' : 'No unsaved changes';
        }
        if (stateDetail) {
            stateDetail.textContent = dirty
                ? (sections.length
                    ? 'Changed: ' + sections.map((s) => s.dataset.rfcSectionTitle).join(', ')
                    : 'Your last save was not completed. Review and save again.')
                : defaultDetail;
        }
    }

    editor.addEventListener('input', (event) => {
        renderPreview();
        refreshState();
    });

    editor.addEventListener('change', (event) => {
        refreshColumns();
        renderPreview();
        refreshState();
    });

    // Validation runs on click, before the submit event, so a blocked save
    // never leaves the form stuck behind the global duplicate-submit lock.
    saveButton?.addEventListener('click', (event) => {
        if (!dirty) {
            event.preventDefault();
            return;
        }

        const invalid = [...editor.querySelectorAll('input, select')].find((el) => !el.disabled && !el.checkValidity());
        if (invalid) {
            event.preventDefault();
            showTab('layout');
            invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            invalid.focus({ preventScroll: true });
            invalid.reportValidity();
            return;
        }

    });

    // Enter inside a text field must not submit a half-finished form.
    editor.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('input:not([type=checkbox]):not([type=radio])')) {
            event.preventDefault();
        }
    });

    editor.addEventListener('submit', () => {
        submitting = true;
        if (saveButton) {
            saveButton.setAttribute('aria-busy', 'true');
            saveButton.textContent = 'Saving…';
        }
    });

    resetButton?.addEventListener('click', () => {
        if (!window.confirm('Undo all unsaved changes on this page?')) return;
        submitting = true;
        window.location.reload();
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty && !submitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) window.location.reload();
    });

    refreshState();
})();
</script>


<script>
/* SPMU_LIVE_ZOOM_V1: standalone zoom behavior; works when live preview is recloned. */
(() => {
    'use strict';
    const editorRoot = document.querySelector('[data-rfc]');
    const viewport = editorRoot?.querySelector('[data-rfc-live-content]');
    const toolbar = editorRoot?.querySelector('[data-rfc-zoom-toolbar]');
    if (!viewport || !toolbar) return;

    const label = toolbar.querySelector('[data-rfc-zoom-label]');
    const fitWidthButton = toolbar.querySelector('[data-rfc-fit-width]');
    const fitPageButton = toolbar.querySelector('[data-rfc-fit-page]');
    const MIN = .25;
    const MAX = 2;
    let mode = 'width';
    let scale = .7;
    let pending = false;
    let centerOnNext = true;

    const clamp = (value) => Math.max(MIN, Math.min(MAX, value));

    function schedule() {
        if (pending) return;
        pending = true;
        requestAnimationFrame(() => {
            pending = false;
            renderZoom();
        });
    }

    function renderZoom() {
        // The editor's existing JS recreates the paper whenever the form changes.
        const directPaper = [...viewport.children].find(el => el.classList.contains('rfc-paper'));
        if (directPaper) {
            const stage = document.createElement('div');
            stage.className = 'rfc-live-stage';
            stage.setAttribute('data-rfc-live-stage', '');
            const frame = document.createElement('div');
            frame.className = 'rfc-live-page-frame';
            frame.setAttribute('data-rfc-live-frame', '');
            viewport.replaceChildren(stage);
            stage.appendChild(frame);
            frame.appendChild(directPaper);
        }

        const paper = viewport.querySelector('.rfc-live-page-frame > .rfc-paper');
        const stage = viewport.querySelector('[data-rfc-live-stage]');
        const frame = viewport.querySelector('[data-rfc-live-frame]');
        if (!paper || !stage || !frame) return;

        const portrait = paper.classList.contains('is-portrait');
        const baseWidth = portrait ? 675 : 940;
        paper.style.setProperty('--rfc-live-paper-width', baseWidth + 'px');
        // Measuring intrinsic layout before scaling avoids clipping on zoom-in.
        paper.style.transform = 'none';
        const baseHeight = Math.max(paper.offsetHeight, paper.scrollHeight);
        const maxWidth = Math.max(1, viewport.clientWidth - 36);
        const maxHeight = Math.max(1, viewport.clientHeight - 36);
        if (mode === 'width') scale = clamp(maxWidth / baseWidth);
        if (mode === 'page') scale = clamp(Math.min(maxWidth / baseWidth, maxHeight / baseHeight));
        scale = clamp(scale);

        const pageWidth = Math.ceil(baseWidth * scale);
        const pageHeight = Math.ceil(baseHeight * scale);
        frame.style.width = pageWidth + 'px';
        frame.style.height = pageHeight + 'px';
        stage.style.width = Math.max(viewport.clientWidth, pageWidth + 36) + 'px';
        stage.style.minHeight = Math.max(viewport.clientHeight, pageHeight + 36) + 'px';
        stage.style.padding = '18px';
        paper.style.transform = `scale(${scale})`;
        if (centerOnNext) {
            viewport.scrollLeft = Math.max(0, (viewport.scrollWidth - viewport.clientWidth) / 2);
            centerOnNext = false;
        }
        if (label) label.textContent = Math.round(scale * 100) + '%';
        if (fitWidthButton) fitWidthButton.setAttribute('aria-pressed', mode === 'width' ? 'true' : 'false');
        if (fitPageButton) fitPageButton.setAttribute('aria-pressed', mode === 'page' ? 'true' : 'false');
    }

    function setManual(delta) {
        mode = 'manual';
        centerOnNext = true;
        // Adjust the current zoom by ten percentage points.
        scale = clamp(Math.round((scale + delta) * 100) / 100);
        schedule();
    }
    toolbar.querySelector('[data-rfc-zoom-out]')?.addEventListener('click', () => setManual(-.1));
    toolbar.querySelector('[data-rfc-zoom-in]')?.addEventListener('click', () => setManual(.1));
    fitWidthButton?.addEventListener('click', () => { mode = 'width'; centerOnNext = true; schedule(); });
    fitPageButton?.addEventListener('click', () => { mode = 'page'; centerOnNext = true; schedule(); });

    // Preserve chosen zoom after Add/Remove/Rename, reordering, size or orientation changes.
    new MutationObserver(schedule).observe(viewport, { childList: true });
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(schedule).observe(viewport);
    else window.addEventListener('resize', schedule);
    schedule();
})();
</script>

@endsection
