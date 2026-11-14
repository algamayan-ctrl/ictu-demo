@php
    /*
    |--------------------------------------------------------------------------
    | Borrower - My Obligations
    |--------------------------------------------------------------------------
    |
    | Related technical records are intentionally grouped into one borrower
    | obligation. A Property Incident + linked Billing + linked Restriction is
    | one obligation here, not three separate cards. The grouping/action-state
    | comes from BorrowerObligationService, which is also used by the borrower
    | dashboard so both screens always agree.
    */

    $obligationRows = app(\App\Services\BorrowerObligationService::class)->buildRows(
        $openIncidents,
        $openOverdueCases,
        $openBillings,
        $activeRestrictions
    );

    $obligationCollection = collect($obligationRows);
    $obligationCount = $obligationCollection->count();
    $needsActionCount = $obligationCollection
        ->where('action_state', \App\Services\BorrowerObligationService::ACTION_BORROWER)
        ->count();
    $resolvedCount = $resolvedHistory->count();

    $obligationService = app(\App\Services\BorrowerObligationService::class);

    /* Remaining unpaid balance - the same figure the dashboard overview uses. */
    $outstandingBalance = $obligationService->outstandingBalance($openBillings);

    $activeRestrictionCount = $activeRestrictions->count();

    /*
     * Decided by the same rule that accepts or blocks a new borrowing
     * request, so this card never reads "Clear" while a request would be
     * refused for an open case or unpaid billing that carries no restriction.
     */
    $borrowingStatusState = $obligationService->borrowingStatus((int) auth()->id());
    $borrowingStatus = $borrowingStatusState['label'];
    $borrowingBlocked = $borrowingStatus !== 'Clear';

    /*
     * Every overview card is a real drill-down even when its value is zero.
     * This keeps the interaction consistent with the role dashboards and lets
     * a borrower click a zero/clear card to see the matching compact empty
     * state instead of presenting a card that only looks interactive.
     */
    $focus = request('focus', 'active');
    $focus = in_array($focus, ['active', 'action', 'payment', 'status'], true) ? $focus : 'active';

    $openBillingNumbers = $openBillings->filter(function ($billing): bool {
        $verified = (float) $billing->payments->where('status', 'VERIFIED')->sum('amount');
        return max(0, (float) $billing->total_amount - $verified) > 0;
    })->pluck('billing_no')->filter()->values();
    $focusedRows = $obligationCollection->filter(function (array $row) use ($focus, $openBillingNumbers): bool {
        return match ($focus) {
            'action' => ($row['action_state'] ?? null) === \App\Services\BorrowerObligationService::ACTION_BORROWER,
            'payment' => $openBillingNumbers->contains(
                fn ($billingNo) => str_contains((string) ($row['search'] ?? ''), strtolower((string) $billingNo))
            ),
            'status' => (bool) ($row['restricted'] ?? false),
            default => true,
        };
    })->values();

    $focusMeta = match ($focus) {
        'action' => ['Needs My Action', 'No action required from you', 'No current obligation requires your response.'],
        'payment' => ['Payment Obligations', 'No payment currently due', 'There is no outstanding payment requirement on your account.'],
        'status' => ['Borrowing Restrictions', 'No active borrowing restriction', 'Your borrowing status is currently clear.'],
        default => ['Your Current Cases', 'No unresolved obligations', 'No active obligations.'],
    };

    // Keep the summary focused on the current borrower outcome; detailed
    // obligations below still use the service-provided rows and actions.
    $hasCurrentAccountability = $obligationCount > 0 || $outstandingBalance > 0 || $borrowingBlocked;
@endphp

@include('accountability.partials.obligations-styles')

<section class="content-area ob-workspace" data-borrower-accountability-clean>
    {{-- Keep the two account indicators visible without restoring the oversized KPI row.
         Each link retains the original focused obligation/empty-state view. --}}
    <div class="ob-overview-metrics" aria-label="Account balance and borrowing status">
        <a class="ob-metric-card {{ $outstandingBalance > 0 ? 'is-unsettled' : 'is-blue' }}"
            href="{{ route('accountability.index', ['focus' => 'payment']) }}#current-obligations">
            <span class="ob-metric-icon" aria-hidden="true"><x-icon name="coins" size="21" /></span>
            <span class="ob-metric-content">
                <span class="ob-metric-label">My Unsettled Charges</span>
                <strong class="ob-metric-value">PHP {{ number_format($outstandingBalance, 2) }}</strong>
                <span class="ob-metric-note">{{ $outstandingBalance > 0 ? 'Unpaid billing balance' : 'No unsettled charges' }}</span>
            </span>
            <span class="ob-metric-arrow" aria-hidden="true"><x-icon name="arrow-right" size="15" /></span>
        </a>
        <a class="ob-metric-card {{ $borrowingBlocked ? 'is-restricted' : 'is-green' }}"
            href="{{ route('accountability.index', ['focus' => $activeRestrictionCount > 0 || ! $borrowingBlocked ? 'status' : 'active']) }}#current-obligations">
            <span class="ob-metric-icon" aria-hidden="true"><x-icon :name="$borrowingBlocked ? 'lock' : 'check-circle'" size="21" /></span>
            <span class="ob-metric-content">
                <span class="ob-metric-label">Borrowing Status</span>
                <strong class="ob-metric-value">{{ $borrowingStatus }}</strong>
                <span class="ob-metric-note">{{ $borrowingStatusState['note'] }}</span>
            </span>
            <span class="ob-metric-arrow" aria-hidden="true"><x-icon name="arrow-right" size="15" /></span>
        </a>
    </div>

    {{-- Compact current-accountability panel; original cases and actions remain intact. --}}
    <div class="ob-current-status {{ $hasCurrentAccountability ? 'is-attention' : 'is-clear' }}"
        aria-label="Current accountability status" role="status">
        <span class="ob-current-status-icon" aria-hidden="true">
            <x-icon :name="$hasCurrentAccountability ? 'warning' : 'check-circle'" size="21" />
        </span>
        <div class="ob-current-status-copy">
            <strong>{{ $hasCurrentAccountability ? 'Current Accountability' : 'No Active Accountability' }}</strong>
            @if($hasCurrentAccountability)
                <p>
                    @if($obligationCount > 0)
                        {{ $obligationCount }} {{ \Illuminate\Support\Str::plural('unresolved obligation', $obligationCount) }}
                    @else
                        There is an outstanding account requirement.
                    @endif
                    @if($needsActionCount > 0)
                        · {{ $needsActionCount }} {{ \Illuminate\Support\Str::plural('item', $needsActionCount) }} {{ $needsActionCount === 1 ? 'requires' : 'require' }} your action
                    @endif
                </p>
                @if($outstandingBalance > 0 || $borrowingBlocked)
                    <div class="ob-current-status-facts">
                        @if($outstandingBalance > 0)
                            <span><strong>Unsettled charges:</strong> PHP {{ number_format($outstandingBalance, 2) }}</span>
                        @endif
                        @if($borrowingBlocked)
                            <span><strong>Borrowing status:</strong> {{ $borrowingStatus }}</span>
                        @endif
                    </div>
                @endif
            @else
                <p>You have no unsettled obligations. Your borrowing status is clear.</p>
            @endif
        </div>
        @if($hasCurrentAccountability)
            <span class="ob-current-status-badge">{{ $needsActionCount > 0 ? 'Action Required' : 'In Progress' }}</span>
        @endif
    </div>

    {{-- Keep initial clear state short; focused metric links can show an empty-state explanation. --}}
    @if($hasCurrentAccountability || $focus !== 'active')
    <div id="current-obligations" class="ob-focused-results">
    @if($focusedRows->isNotEmpty())
        <div class="ob-current-header">
            <h2>
                {{ $focusMeta[0] }}
                <span class="accountability-count-chip">{{ $focusedRows->count() }}</span>
            </h2>

            @if($focusedRows->count() > 5)
                <label class="ob-search">
                    <span>Search</span>
                    <span class="search-input-shell">
                        <span class="search-input-icon" aria-hidden="true"><x-icon name="search" size="17" /></span>
                        <input
                            type="search"
                            placeholder="Search obligations..."
                            autocomplete="off"
                            data-obligation-clean-search
                        >
                    </span>
                </label>
            @endif
        </div>

        <div class="ob-case-list" data-obligation-clean-list>
            @foreach($focusedRows as $index => $row)
                <article
                    class="ob-case-card"
                    data-obligation-clean-row
                    data-search="{{ $row['search'] }}"
                >
                    <div class="ob-case-top">
                        <div class="ob-case-identity">
                            <span class="ob-case-icon is-{{ $row['tone'] }}" aria-hidden="true">
                                <x-icon :name="$row['icon']" size="20" />
                            </span>

                            <div>
                                <span class="ob-case-type">{{ $row['type'] }}</span>
                                <h3>{{ $row['title'] }}</h3>
                                <small>{{ $row['reference'] }}</small>
                            </div>
                        </div>

                        <div class="ob-case-state">
                            <span class="ob-badge is-{{ $row['badge_tone'] }}">{{ $row['badge'] }}</span>
                            @if($row['status_meta'])
                                <strong>{{ $row['status_meta'] }}</strong>
                            @endif
                        </div>
                    </div>

                    <p class="ob-case-summary">{{ $row['summary'] }}</p>

                    @if($row['restricted'] && ($row['type'] ?? '') !== 'Borrowing Restriction')
                        <div class="ob-linked-restriction">
                            <x-icon name="lock" size="15" />
                            <span>Borrowing restricted until resolved.</span>
                        </div>
                    @endif

                    <div class="ob-next-action is-{{ $row['next_tone'] }}">
                        <span>{{ $row['action_state'] === \App\Services\BorrowerObligationService::ACTION_BORROWER ? 'Next step' : 'Current status' }}</span>
                        <strong>{{ $row['next_action'] }}</strong>
                    </div>

                    @php
                        /*
                         * One obligation = one obvious primary action: View
                         * Obligation. Every document/reference action this row
                         * carries only appears after it is opened, grouped by
                         * what it actually is, instead of a row of buttons
                         * competing with each other on the collapsed card.
                         */
                        $documentActions = collect($row['actions'])->filter(fn ($action) => ($action[4] ?? 'document') === 'document');
                        $referenceActions = collect($row['actions'])->filter(fn ($action) => ($action[4] ?? 'document') === 'reference');
                        $obligationRestriction = $row['restricted'] ? ($row['restriction'] ?? null) : null;
                        $hasHistoryFacts = ! empty($row['facts']);
                    @endphp

                    <div class="ob-case-actions">
                        <details class="ob-details">
                            <summary class="button secondary small ui-pressable disclosure-trigger"><span>View Details</span><x-icon name="chevron-down" size="14" class="disclosure-trigger__chevron" /></summary>
                            <div class="ob-detail-body">
                                @if($documentActions->isNotEmpty())
                                    <div class="ob-detail-section">
                                        <p class="ob-detail-section-heading">Documents</p>
                                        <div class="ob-document-list">
                                            @foreach($documentActions as $documentAction)
                                                @php
                                                    [$label, $url, $newTab, $buttonTone] = $documentAction;
                                                    $documentName = $documentAction[5] ?? 'Document';
                                                @endphp
                                                <div class="ob-document-item">
                                                    <span class="ob-document-name">
                                                        <x-icon name="document" size="14" />
                                                        <strong>{{ $documentName }}</strong>
                                                    </span>
                                                    <a
                                                        class="table-action ui-pressable"
                                                        href="{{ $url }}"
                                                        @if($newTab) target="_blank" rel="noopener" @endif
                                                    >
                                                        {{ $label }}
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if($referenceActions->isNotEmpty())
                                    <div class="ob-detail-section">
                                        <p class="ob-detail-section-heading">Borrowing Reference</p>
                                        <div class="ob-detail-actions">
                                            @foreach($referenceActions as [$label, $url, $newTab, $buttonTone])
                                                <a
                                                    class="button {{ $buttonTone === 'primary' ? 'primary' : 'secondary' }} small ui-pressable"
                                                    href="{{ $url }}"
                                                    @if($newTab) target="_blank" rel="noopener" @endif
                                                >
                                                    {{ $label }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if($obligationRestriction)
                                    <div class="ob-detail-section">
                                        <p class="ob-detail-section-heading">Restriction</p>
                                        <div class="ob-detail-grid">
                                            <div>
                                                <small>Reason</small>
                                                <strong>{{ $obligationRestriction->reason ?: str($obligationRestriction->restriction_type)->replace('_', ' ')->title() }}</strong>
                                            </div>
                                            <div>
                                                <small>Effective From</small>
                                                <strong>{{ optional($obligationRestriction->effective_from)->format('d M Y') ?: '—' }}</strong>
                                            </div>
                                            <div>
                                                <small>Status</small>
                                                <strong>{{ $obligationRestriction->effective_to ? 'In effect until '.$obligationRestriction->effective_to->format('d M Y') : 'Active until resolved' }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if($hasHistoryFacts)
                                    <div class="ob-detail-section">
                                        <p class="ob-detail-section-heading">History / Details</p>
                                        <div class="ob-detail-grid">
                                            @foreach($row['facts'] as [$factLabel, $factValue])
                                                <div>
                                                    <small>{{ $factLabel }}</small>
                                                    <strong>{{ $factValue }}</strong>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </details>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="ob-no-match" data-obligation-clean-empty hidden>
            No outstanding obligations match the search criteria.
        </div>
    @else
        @if(! $hasCurrentAccountability)
            <article class="ob-clear-card" id="obligations-empty">
                <span aria-hidden="true"><x-icon name="check-circle" size="22" /></span>
                <div>
                    <strong>{{ $focusMeta[1] }}</strong>
                    <p>{{ $focusMeta[2] }}</p>
                </div>
            </article>
        @elseif($obligationCount === 0)
            <article class="ob-clear-card" id="obligations-empty">
                <span aria-hidden="true"><x-icon name="info" size="22" /></span>
                <div>
                    <strong>Account status requires review.</strong>
                    <p>{{ $borrowingStatusState['note'] ?? 'Contact SPMU for your account details.' }}</p>
                </div>
            </article>
        @else
            <article class="ob-clear-card" id="obligations-empty">
                <span aria-hidden="true"><x-icon name="check-circle" size="34" /></span>
                <div>
                    <strong>{{ $focusMeta[1] }}</strong>
                    <p>{{ $focusMeta[2] }}</p>
                </div>
            </article>
        @endif
    @endif
    </div>
    @endif

    @if($resolvedCount > 0)
        <details class="ob-resolved-history-disclosure" id="obligation-history">
            <summary>
                <span>Obligation History</span>
                <span class="ob-resolved-history-meta">
                    <span class="ob-badge is-neutral">{{ $resolvedCount }} {{ $resolvedCount === 1 ? 'record' : 'records' }}</span>
                    <x-icon name="chevron-down" size="16" class="ob-disclosure-chevron" />
                </span>
            </summary>
            <div class="ob-resolved-history-body">
                @include('accountability.partials.resolved-history', ['embeddedInBorrowerWorkspace' => true])
            </div>
        </details>
    @endif
</section>

<script>
(() => {
    const workspace = document.querySelector('[data-borrower-accountability-clean]');
    if (!workspace) return;

    const input = workspace.querySelector('[data-obligation-clean-search]');
    const rows = Array.from(workspace.querySelectorAll('[data-obligation-clean-row]'));
    const empty = workspace.querySelector('[data-obligation-clean-empty]');

    if (!input || rows.length === 0) return;

    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        let visible = 0;

        rows.forEach((row) => {
            const matches = !query || (row.dataset.search || '').includes(query);
            row.hidden = !matches;
            if (matches) visible += 1;
        });

        if (empty) empty.hidden = visible !== 0;
    });
})();
</script>
