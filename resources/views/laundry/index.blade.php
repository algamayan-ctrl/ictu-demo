@extends('layouts.app', ['title' => 'Laundry Operations'])
@section('content')
@php
    // Read-only display relations, batch-loaded for the existing paginated results.
    $jobs->getCollection()->loadMissing([
        'custody.request.currentVersion',
        'custody.borrower.organizationalUnit',
        'custody.returns',
    ]);
    $completedJobs->getCollection()->loadMissing([
        'custody.borrower.organizationalUnit',
        'custody.request',
    ]);

    $hasLaundryCases = $jobs->total() > 0;
    $hasCompletedCases = $completedJobs->total() > 0;

    /*
     * Filter state follows the borrower-facing Laundry workflow, including the
     * important distinction between a pending form and an accomplished form
     * that is already ready for SPMU encoding.
     */
    $laundryFilterStatus = static function ($job): array {
        return match (true) {
            $job->status === 'FOR_LAUNDRY' && $job->hasVerifiedAccomplishedForm()
                => ['key' => 'READY_FOR_SPMU_ENCODING', 'label' => 'Ready for SPMU Encoding'],
            $job->status === 'FOR_LAUNDRY'
                => ['key' => 'LAUNDRY_FORM_PENDING', 'label' => 'Laundry Form Pending'],
            $job->status === 'TURNED_OVER_TO_LAUNDRY'
                => ['key' => 'RECONCILIATION_PENDING', 'label' => 'Reconciliation Pending'],
            $job->status === 'LAUNDRY_COMPLETED'
                => ['key' => 'AVAILABLE', 'label' => 'Available'],
            default
                => ['key' => strtoupper((string) $job->status), 'label' => $job->displayStatusLabel()],
        };
    };

    $laundryStatuses = collect([
        ['key' => 'LAUNDRY_FORM_PENDING', 'label' => 'Laundry Form Pending'],
        ['key' => 'READY_FOR_SPMU_ENCODING', 'label' => 'Ready for SPMU Encoding'],
        ['key' => 'RECONCILIATION_PENDING', 'label' => 'Reconciliation Pending'],
    ]);
@endphp

@include('laundry.partials.operations-styles')

<div class="laundry-operations" data-laundry-browser>
    <section class="page-heading laundry-operations-heading">
        <div>
            <p class="eyebrow">SPMU Action Officer</p>
            <h1>Laundry Operations</h1>
            <p>Monitor active linen cases and review completed laundry records in one workspace.</p>
        </div>
    </section>

    <div class="card laundry-unified-workspace" data-laundry-tabs>
        <div class="laundry-tabs" role="tablist" aria-label="Laundry case status">
            <button type="button" class="laundry-tab is-selected" id="laundry-tab-active" role="tab" aria-controls="laundry-panel-active" aria-selected="true" data-laundry-tab="active">Active <span class="laundry-tab-count">{{ $jobs->total() }}</span></button>
            <button type="button" class="laundry-tab" id="laundry-tab-completed" role="tab" aria-controls="laundry-panel-completed" aria-selected="false" tabindex="-1" data-laundry-tab="completed">Completed <span class="laundry-tab-count">{{ $completedJobs->total() }}</span></button>
        </div>
        <div id="laundry-panel-active" role="tabpanel" aria-labelledby="laundry-tab-active" data-laundry-panel="active">
        <section class="card record-browser-toolbar laundry-toolbar" aria-label="Active laundry case filters">
            <label class="record-browser-search" for="laundry-search">Search
                <span class="search-input-shell">
                    <span class="search-input-icon" aria-hidden="true"><x-icon name="search" size="17" /></span>
                    <input id="laundry-search" type="search" placeholder="Search borrower, request no., custody no., or event..." data-laundry-search autocomplete="off">
                </span>
            </label>
            <label for="laundry-status">Status
                <select id="laundry-status" data-laundry-status>
                    <option value="">All statuses</option>
                    @foreach($laundryStatuses as $status)
                        <option value="{{ $status['key'] }}">{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label for="laundry-sort">Sort
                <select id="laundry-sort" data-laundry-sort>
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                </select>
            </label>
        </section>

    <section class="card laundry-cases-card" aria-labelledby="laundry-cases-title">
        <div class="laundry-cases-heading">
            <h2 id="laundry-cases-title">Active Laundry Cases</h2>
            <p data-laundry-count data-total="{{ $jobs->total() }}" data-paginated="{{ $jobs->hasPages() ? 'true' : 'false' }}" role="status" aria-live="polite">
                @if($hasLaundryCases)
                    Showing {{ $jobs->count() }} of {{ $jobs->total() }} cases
                @else
                    No active cases
                @endif
            </p>
        </div>

        @if($hasLaundryCases)
            <div class="laundry-table-wrap">
                <table class="laundry-cases-table">
                    <thead>
                        <tr><th scope="col">Request No.</th><th scope="col">Borrower</th><th scope="col">Event / Purpose</th><th scope="col">Custody No.</th><th scope="col">Status</th><th scope="col">Last Update</th><th scope="col">Actions</th></tr>
                    </thead>
                    <tbody data-laundry-list>
                        @foreach($jobs as $job)
                            @php
                                $caseCustody = $job->custody;
                                $caseBorrower = $caseCustody?->borrower;
                                $caseRequest = $caseCustody?->request;
                                $casePurpose = $caseRequest?->currentVersion?->purpose_event;
                                $statusText = $job->displayStatusLabel();
                                $statusDescription = $job->displayStatusDescription();
                                $filterStatus = $laundryFilterStatus($job);
                                $caseSearch = implode(' ', [
                                    $job->id,
                                    $caseRequest?->request_no,
                                    $caseCustody?->custody_no,
                                    $caseBorrower?->full_name,
                                    $caseBorrower?->organizationalUnit?->unit_name,
                                    $casePurpose,
                                    $job->lines->map(fn ($line) => $line->custodyLine?->requestItem?->description_snapshot)->implode(' '),
                                ]);
                            @endphp
                            <tr data-laundry-record data-search="{{ $caseSearch }}" data-status="{{ $filterStatus['key'] }}" data-date="{{ $job->updated_at?->timestamp ?? 0 }}">
                                <td><a class="laundry-request-link" href="{{ route('laundry.show', $job) }}">{{ $caseRequest?->request_no ?: 'Laundry case #'.$job->id }}</a></td>
                                <td><strong>{{ $caseBorrower?->full_name ?: '—' }}</strong><small>{{ $caseBorrower?->organizationalUnit?->unit_name ?: '—' }}</small></td>
                                <td>{{ $casePurpose ?: '—' }}</td>
                                <td>{{ $caseCustody?->custody_no ?: '—' }}</td>
                                <td><x-status-badge :status="$job->status" :label="$statusText" :title="$statusDescription" /></td>
                                <td class="laundry-returned-date">{{ $job->updated_at?->format('M d, Y') ?: '—' }}</td>
                                <td><a class="button secondary small ui-pressable laundry-view-link" href="{{ route('laundry.show', $job) }}"><span>View Record</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="laundry-filter-empty" data-laundry-filter-empty hidden>
                <x-icon name="search" size="27" />
                <strong>No laundry cases match your filters.</strong>
                <p>Try another search or status.</p>
                <button class="button secondary small ui-pressable" type="button" data-laundry-reset>Clear filters</button>
            </div>
            @if($jobs->hasPages())
                <div class="laundry-pagination">{{ $jobs->links('partials.pagination') }}</div>
            @endif
        @else
            <div class="laundry-inline-empty">
                @include('laundry.partials.empty-illustration')
                <div>
                    <strong>No active laundry cases need action.</strong>
                    <p>New linen cases will appear here after physical release.</p>
                </div>
            </div>
        @endif
    </section>

        </div>
        <div id="laundry-panel-completed" role="tabpanel" aria-labelledby="laundry-tab-completed" data-laundry-panel="completed" hidden>
    <div class="laundry-completed-block" id="completed-laundry-cases" data-completed-laundry>
        @if($hasCompletedCases)
            <section class="card record-browser-toolbar laundry-toolbar laundry-completed-toolbar" aria-label="Completed laundry case filters">
                <label class="record-browser-search" for="completed-laundry-search">Search
                    <span class="search-input-shell">
                        <span class="search-input-icon" aria-hidden="true"><x-icon name="search" size="17" /></span>
                        <input id="completed-laundry-search" type="search" placeholder="Search borrower, request no., custody no., or items..." data-completed-search autocomplete="off">
                    </span>
                </label>
                <label for="completed-laundry-outcome">Outcome
                    <select id="completed-laundry-outcome" data-completed-outcome>
                        <option value="">All outcomes</option>
                        <option value="available">Available</option>
                        <option value="accountability">Accountability required</option>
                        <option value="completed">Completed</option>
                    </select>
                </label>
                <label for="completed-laundry-sort">Sort
                    <select id="completed-laundry-sort" data-completed-sort>
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                    </select>
                </label>
            </section>
        @endif

        <section class="card laundry-cases-card laundry-completed-card" aria-labelledby="completed-laundry-title">
            <div class="laundry-cases-heading">
                <h2 id="completed-laundry-title">Completed Laundry Cases</h2>
                <p data-completed-count
                   data-total="{{ $completedJobs->total() }}"
                   data-first="{{ $completedJobs->firstItem() ?? 0 }}"
                   data-last="{{ $completedJobs->lastItem() ?? 0 }}"
                   data-paginated="{{ $completedJobs->hasPages() ? 'true' : 'false' }}"
                   role="status"
                   aria-live="polite">
                    @if($hasCompletedCases)
                        Showing {{ $completedJobs->firstItem() ?? 0 }} to {{ $completedJobs->lastItem() ?? 0 }} of {{ $completedJobs->total() }} completed cases
                    @else
                        No completed cases
                    @endif
                </p>
            </div>

            @if($hasCompletedCases)
                <div class="laundry-table-wrap">
                    <table class="laundry-cases-table laundry-completed-table">
                        <thead>
                            <tr><th scope="col">Request No.</th><th scope="col">Borrower</th><th scope="col">Items</th><th scope="col">Completed Date</th><th scope="col">Outcome</th><th scope="col">Actions</th></tr>
                        </thead>
                        <tbody data-completed-list>
                            @foreach($completedJobs as $job)
                                @php
                                    $completedBorrower = $job->custody?->borrower;
                                    $completedRequest = $job->custody?->request;
                                    $serviceableQuantity = (int) $job->lines->sum('completed_quantity');
                                    $hasRecordedQuantities = $job->lines->isNotEmpty()
                                        && $job->lines->every(fn ($line) => $line->received_quantity !== null);

                                    $adverseConditions = $job->lines
                                        ->flatMap(fn ($line) => $line->custodyLine?->returnLines ?? collect())
                                        ->map(fn ($returnLine) => strtoupper((string) $returnLine->condition_code))
                                        ->filter(fn ($condition) => $condition !== '' && $condition !== 'FINE')
                                        ->unique()
                                        ->values();
                                    $hasAdverseFinding = $adverseConditions->isNotEmpty();

                                    $expectedReturn = $job->custody?->original_due_at;
                                    $isLateReturn = $job->worker_received_at && $expectedReturn
                                        ? $job->worker_received_at->copy()->startOfDay()->gt($expectedReturn->copy()->startOfDay())
                                        : false;
                                    $hasAccountability = $hasAdverseFinding || $isLateReturn;

                                    $caseOutcomes = array_filter([
                                        $serviceableQuantity > 0 ? 'available' : null,
                                        $hasAccountability ? 'accountability' : null,
                                    ]);
                                    if (! $caseOutcomes) {
                                        $caseOutcomes = ['completed'];
                                    }

                                    $adverseReason = $adverseConditions
                                        ->map(fn ($condition) => match ($condition) {
                                            'DAMAGED' => 'Damaged Item',
                                            'DESTROYED' => 'Destroyed Item',
                                            'MISSING' => 'Missing Item',
                                            'LOST' => 'Lost Item',
                                            'STOLEN' => 'Stolen Item',
                                            default => ucwords(strtolower(str_replace('_', ' ', $condition))),
                                        })
                                        ->implode(' + ');
                                    $accountabilityReason = collect([
                                        $isLateReturn ? 'Late Return' : null,
                                        $hasAdverseFinding ? $adverseReason : null,
                                    ])->filter()->implode(' + ') ?: null;

                                    $itemSummary = $hasRecordedQuantities
                                        ? $job->lines->groupBy(fn ($line) => $line->custodyLine?->requestItem?->unit_snapshot ?: 'items')
                                            ->map(function ($lines, $unit) {
                                                $quantity = (int) $lines->sum('received_quantity');
                                                $label = match(strtolower($unit)) {
                                                    'piece', 'pieces', 'pc', 'pcs' => 'pcs',
                                                    'unit', 'units' => $quantity === 1 ? 'unit' : 'units',
                                                    default => $unit,
                                                };
                                                return $quantity.' '.$label;
                                            })->implode(' · ')
                                        : '—';

                                    $completedDate = $job->completed_at ?: $job->worker_completed_at;
                                    $completedSearch = implode(' ', [
                                        $job->id,
                                        $completedRequest?->request_no,
                                        $job->custody?->custody_no,
                                        $completedBorrower?->full_name,
                                        $completedBorrower?->organizationalUnit?->unit_name,
                                        $job->lines->map(fn ($line) => $line->custodyLine?->requestItem?->description_snapshot)->implode(' '),
                                    ]);
                                @endphp
                                <tr data-completed-record
                                    data-search="{{ $completedSearch }}"
                                    data-outcomes="{{ implode(' ', $caseOutcomes) }}"
                                    data-created="{{ optional($completedDate)->timestamp ?? optional($job->updated_at)->timestamp ?? optional($job->created_at)->timestamp ?? 0 }}">
                                    <td><a class="laundry-request-link" href="{{ route('laundry.show', $job) }}">{{ $completedRequest?->request_no ?: 'Laundry case #'.$job->id }}</a></td>
                                    <td><strong>{{ $completedBorrower?->full_name ?: '—' }}</strong><small>{{ $completedBorrower?->organizationalUnit?->unit_name ?: '—' }}</small></td>
                                    <td>{{ $itemSummary }}</td>
                                    <td class="laundry-completed-date">
                                        @if($completedDate)
                                            <time datetime="{{ $completedDate->toIso8601String() }}"><span>{{ $completedDate->format('d M Y') }},</span><span>{{ $completedDate->format('h:i A') }}</span></time>
                                        @else
                                            <span>Not recorded</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="laundry-outcomes">
                                            @if($serviceableQuantity > 0)
                                                <span class="laundry-outcome-badge is-available" title="{{ $serviceableQuantity }} serviceable linen available">Available</span>
                                            @endif
                                            @if($hasAccountability)
                                                <span class="laundry-outcome-badge is-accountability" title="Referred to Accountability Processing: {{ $accountabilityReason }}">Accountability Required<br>{{ $accountabilityReason }}</span>
                                            @endif
                                            @if($serviceableQuantity === 0 && ! $hasAccountability)
                                                <span class="laundry-outcome-badge is-neutral">Completed</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td><a class="button secondary small ui-pressable laundry-view-link" href="{{ route('laundry.show', $job) }}"><span>View Record</span></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="laundry-filter-empty" data-completed-empty hidden>
                    <x-icon name="search" size="27" />
                    <strong>No completed cases match your filters.</strong>
                    <p>Try another search or outcome.</p>
                    <button class="button secondary small ui-pressable" type="button" data-completed-reset>Clear filters</button>
                </div>
                @if($completedJobs->hasPages())
                    <div class="laundry-pagination">{{ $completedJobs->links('partials.pagination') }}</div>
                @endif
            @else
                <div class="laundry-inline-empty is-completed">
                    <x-icon name="check-circle" size="34" />
                    <div>
                        <strong>No completed laundry cases yet.</strong>
                        <p>Finished cases will stay visible here for record review.</p>
                    </div>
                </div>
            @endif
        </section>
    </div>
        </div>
    </div>
</div>

@include('laundry.partials.operations-interactions')
@endsection
