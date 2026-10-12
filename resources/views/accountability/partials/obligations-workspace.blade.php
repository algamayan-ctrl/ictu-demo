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
        default => ['Current Obligations', 'No unresolved obligations', 'No active obligations.'],
    };

    // A zero-action card repeats the empty state; show it only when a response is needed.
    $summaryCards = [
        ['info', 'accountability', (string) $obligationCount, 'Active Obligations', $obligationCount ? 'Unresolved matters' : 'None', route('accountability.index', ['focus' => 'active']).'#current-obligations', 'active'],
    ];
    if ($needsActionCount > 0) {
        $summaryCards[] = ['warning', 'warning', (string) $needsActionCount, 'Needs My Action', 'Your response is required', route('accountability.index', ['focus' => 'action']).'#current-obligations', 'action'];
    }
    $summaryCards[] = [$outstandingBalance > 0 ? 'orange' : 'blue', 'coins', 'PHP '.number_format($outstandingBalance, 2), 'Amount Due', $outstandingBalance > 0 ? 'Unpaid billing balance' : 'No payment due', route('accountability.index', ['focus' => 'payment']).'#current-obligations', 'payment'];
    $summaryCards[] = [$borrowingBlocked ? 'warning' : 'success', $borrowingBlocked ? 'lock' : 'check-circle', $borrowingStatus, 'Borrowing Status', $borrowingStatusState['note'], route('accountability.index', ['focus' => $activeRestrictionCount > 0 || ! $borrowingBlocked ? 'status' : 'active']).'#current-obligations', 'status'];
@endphp

@include('accountability.partials.obligations-styles')

<section class="content-area ob-workspace" data-borrower-accountability-clean>
    <div class="ob-summary {{ $needsActionCount === 0 ? 'is-three' : '' }}" aria-label="My obligations overview">
        @foreach($summaryCards as [$tone, $icon, $value, $label, $note, $href, $cardFocus])
            <a
                class="ob-summary-card is-{{ $tone }} is-clickable {{ $focus === $cardFocus ? 'is-selected' : '' }}"
                href="{{ $href }}"
                @if($focus === $cardFocus) aria-current="true" @endif
            >
                <span class="ob-summary-icon" aria-hidden="true">
                    <x-icon :name="$icon" size="20" />
                </span>
                <span class="ob-summary-label">{{ $label }}</span>
                <strong class="ob-summary-value">{{ $value }}</strong>
                <span class="ob-summary-note">{{ $note }}</span>
            </a>
        @endforeach
    </div>

    {{-- Do not reserve an empty grid row between the KPI cards and history when all obligations are resolved. --}}
    @if($obligationCount > 0 || $resolvedCount === 0)
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
        @if($obligationCount === 0)
            @if($resolvedCount === 0)
                <article class="ob-clear-card ob-brief-empty" id="obligations-empty">
                    <span aria-hidden="true"><x-icon name="check-circle" size="20" /></span>
                    <strong>No current obligations.</strong>
                </article>
            @endif
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
