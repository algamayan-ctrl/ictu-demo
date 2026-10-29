{{--
    Resolved accountability history.

    Read-only. Every figure comes from records that already exist - the frozen
    late-return assessment on the overdue case, the billing, and its verified
    payment. Nothing here can reopen or change a case, and no outcome is
    relabelled: only a settled billing with a verified payment reads as Paid.

    Shared by the staff Accountability page and the borrower's My Obligations
    page. Staff (AO/Head) see a compact table, one row per case, matching the
    same universal table used by Current Accountability - this partial is
    the only thing that differs between them. The borrower's own view keeps
    its existing card layout unchanged; that page was not part of this
    redesign.
--}}
@php
    /*
     * Historical records stay secondary to current work: only the most
     * recent rows render open by default, and the rest reveal on request
     * instead of loading a long table/list into view.
     */
    $resolvedHistoryInitialLimit = 5;
    $embeddedInBorrowerWorkspace = $embeddedInBorrowerWorkspace ?? false;
@endphp
<section class="content-area" id="resolved-history">
@if($isBorrower ?? false)
    @unless($embeddedInBorrowerWorkspace)
    <div class="section-heading accountability-section-heading">
        <div>
            <p class="eyebrow">Closed records</p>
            <h2>Resolved Accountability</h2>
            <p>Read-only. Settled, waived and voided outcomes are shown as they were recorded.</p>
        </div>

        @if($resolvedHistory->isNotEmpty())
            <span class="status-badge status-neutral">
                {{ $resolvedHistory->count() }}
                {{ $resolvedHistory->count() === 1 ? 'record' : 'records' }}
            </span>
        @endif
    </div>
    @endunless

    @if($resolvedHistory->isEmpty())
        <article class="card top-gap accountability-empty">
            <strong>No resolved accountability cases yet.</strong>
            <span>Cases appear here after payment verification or another final resolution.</span>
        </article>
    @else
        @foreach($resolvedHistory as $resolvedHistoryIndex => $row)
            @php
                $case = $row['case'];
                $incident = $row['incident'] ?? null;
                $billing = $row['billing'];
                $payment = $row['payment'];
                $fromLaundry = $case?->return_date_source === 'LAUNDRY_RECEIPT';

                /*
                 * resolvedHistory() only ever builds a row from one of two
                 * sources: a billing tied back to a late-return overdue case
                 * ($case present), or a case that closed with no billing at
                 * all. Anything else - a property-case billing, for example -
                 * has no $case, so it is Property Accountability instead of
                 * Late Return. Nothing here recomputes an outcome; it only
                 * labels the two sources the array already distinguishes.
                 */
                $typeLabel = $case
                    ? (((int) ($case->late_days ?? 0)) > 0
                        ? ($fromLaundry ? 'Laundry-Reported Late Return' : 'Late Return')
                        : 'Overdue Record Cleared')
                    : 'Property Accountability';
                $amountLabel = $billing ? 'PHP '.number_format((float) $billing->total_amount, 2) : 'No charge';
                $lateReturnNotice = $case?->documents
                    ?->where('document_type', 'LATE_RETURN_NOTICE')
                    ->whereNotIn('status', ['SUPERSEDED', 'INVALIDATED', 'EXPIRED'])
                    ->sortByDesc('generated_at')
                    ->first();

                /*
                 * This partial is shared by the AO/Head Accountability page
                 * and Borrower My Obligations. Which staff member handled a
                 * step is an internal operational detail, not a fact about
                 * the borrower's own case, so it is shown only to SPMU staff.
                 */
                $showsStaffIdentity = ! ($isBorrower ?? false);
            @endphp

            <article
                class="card top-gap accountability-case-card resolved-history-card"
                @if($resolvedHistoryIndex >= $resolvedHistoryInitialLimit) hidden data-resolved-history-extra @endif
            >
                <div class="card-header">
                    <div>
                        <strong>{{ $row['reference'] }}</strong>
                        @unless($isBorrower ?? false)
                        <h3>{{ $row['borrower']?->full_name ?? 'Unknown borrower' }}</h3>
                        @endunless
                        <small>{{ $typeLabel }}</small>
                    </div>

                    <x-status-badge :status="$row['tone'] === 'success' ? 'COMPLETED' : 'CANCELLED'" :label="$row['outcome']" />
                </div>

                <div class="resolved-history-summary">
                    <span>{{ $amountLabel }}</span>
                    @if($row['resolved_at'])
                        <span>Resolved {{ \Carbon\Carbon::parse($row['resolved_at'])->format('d M Y') }}</span>
                    @endif
                </div>

                <details class="accountability-case-details">
                    <summary class="resolved-history-detail-toggle button secondary small ui-pressable disclosure-trigger"><span>View Details</span><x-icon name="chevron-down" size="14" class="disclosure-trigger__chevron" /></summary>
                    @include('accountability.partials.resolved-history-detail')
                </details>
            </article>
        @endforeach

        @if($resolvedHistory->count() > $resolvedHistoryInitialLimit)
            <button type="button" class="button secondary top-gap" id="resolved-history-show-more">
                Show {{ $resolvedHistory->count() - $resolvedHistoryInitialLimit }} More
            </button>
            <script>
            (() => {
                const button = document.getElementById('resolved-history-show-more');
                if (!button) return;
                button.addEventListener('click', () => {
                    document.querySelectorAll('[data-resolved-history-extra]').forEach(row => { row.hidden = false; });
                    button.remove();
                }, { once: true });
            })();
            </script>
        @endif
    @endif
@else
    <article class="card accountability-cases-card {{ $embeddedInBorrowerWorkspace ? 'accountability-history-embedded' : '' }}">
        @unless($embeddedInBorrowerWorkspace)
            <div class="accountability-cases-head">
                <h2>
                    Resolved Accountability
                    @if($resolvedHistory->isNotEmpty())
                        <span class="accountability-count-chip" id="resolved-history-count">{{ $resolvedHistory->count() }}</span>
                    @endif
                </h2>
            </div>
        @endunless

        @if($resolvedHistory->isEmpty())
            <div class="empty-state">
                <div>
                    <strong>No resolved accountability cases yet.</strong>
                    <p>Cases appear here after payment verification or another final resolution.</p>
                </div>
            </div>
        @else
            @unless($embeddedInBorrowerWorkspace)
            {{-- RESOLVED HISTORY FILTERS START --}}
            <div class="accountability-browser-toolbar" aria-label="Search and filter resolved accountability cases">

                <label class="accountability-browser-search">
                    <span>Search</span>
                    <span class="search-input-shell">
                        <span class="search-input-icon" aria-hidden="true">
                            <x-icon name="search" size="16" />
                        </span>

                        <input
                            id="resolved-history-search"
                            type="search"
                            autocomplete="off"
                            placeholder="Borrower or reference"
                        >
                    </span>
                </label>

                <label class="accountability-browser-filter">
                    <span>Case Type</span>

                    <select id="resolved-history-case-type">
                        <option value="all">All cases</option>
                        <option value="Property Accountability">Property Accountability</option>
                        <option value="Late Return">Late Return</option>
                        <option value="Laundry-Reported Late Return">Laundry-Reported Late Return</option>
                        <option value="Overdue Record Cleared">Overdue Record Cleared</option>
                    </select>
                </label>

                <label class="accountability-browser-filter">
                    <span>Outcome</span>

                    <select id="resolved-history-outcome">
                        <option value="all">All outcomes</option>
                        <option value="Resolved - Paid">Resolved - Paid</option>
                        <option value="Resolved - No Charge">Resolved - No Charge</option>
                        <option value="Resolved - Finding Cleared">Resolved - Finding Cleared</option>
                        <option value="Resolved - Accountability Settled">Resolved - Accountability Settled</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Waived">Waived</option>
                        <option value="Void">Void</option>
                    </select>
                </label>

            </div>
            {{-- RESOLVED HISTORY FILTERS END --}}
            @endunless

            <div class="table-wrap accountability-cases-table">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Reference / Borrower</th>
                            <th scope="col">Case Type</th>
                            <th scope="col">Outcome</th>
                            <th scope="col" class="is-numeric">Amount</th>
                            <th scope="col">Resolved Date</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resolvedHistory as $resolvedHistoryIndex => $row)
                            @php
                                $case = $row['case'];
                                $incident = $row['incident'] ?? null;
                                $billing = $row['billing'];
                                $payment = $row['payment'];
                                $fromLaundry = $case?->return_date_source === 'LAUNDRY_RECEIPT';
                                $typeLabel = $case
                                    ? (((int) ($case->late_days ?? 0)) > 0
                                        ? ($fromLaundry ? 'Laundry-Reported Late Return' : 'Late Return')
                                        : 'Overdue Record Cleared')
                                    : 'Property Accountability';
                                $amountLabel = $billing ? 'PHP '.number_format((float) $billing->total_amount, 2) : 'No charge';
                                $lateReturnNotice = $case?->documents
                                    ?->where('document_type', 'LATE_RETURN_NOTICE')
                                    ->whereNotIn('status', ['SUPERSEDED', 'INVALIDATED', 'EXPIRED'])
                                    ->sortByDesc('generated_at')
                                    ->first();
                                $showsStaffIdentity = ! ($isBorrower ?? false);
                            @endphp
                            <tr
                                class="accountability-case-row"
                                data-resolved-history-row
                                data-search="{{ $row['reference'].' '.($row['borrower']?->full_name ?? '') }}"
                                data-case-type="{{ $typeLabel }}"
                                data-outcome="{{ $row['outcome'] }}"
                                @if($resolvedHistoryIndex >= $resolvedHistoryInitialLimit) hidden data-resolved-history-extra @endif
                            >
                                <td>
                                    <span class="accountability-case-ref">{{ $row['reference'] }}</span>
                                    <span class="accountability-case-borrower">{{ $row['borrower']?->full_name ?? 'Unknown borrower' }}</span>
                                </td>
                                <td><span class="accountability-case-type-label">{{ $typeLabel }}</span></td>
                                <td><x-status-badge :status="$row['tone'] === 'success' ? 'COMPLETED' : 'CANCELLED'" :label="$row['outcome']" /></td>
                                <td class="is-numeric">{{ $amountLabel }}</td>
                                <td>{{ $row['resolved_at'] ? \Carbon\Carbon::parse($row['resolved_at'])->format('d M Y') : '—' }}</td>
                                <td>
                                    <button type="button" class="button secondary small ui-pressable disclosure-trigger" data-case-toggle aria-expanded="false">
                                        <span>View Details</span>
                                        <x-icon name="chevron-down" size="13" class="disclosure-trigger__chevron" />
                                    </button>
                                </td>
                            </tr>
                            <tr class="accountability-case-detail-row" hidden>
                                <td colspan="6">
                                    @include('accountability.partials.resolved-history-detail')
                                </td>
                            </tr>
                        @endforeach

                        <tr id="resolved-history-none" hidden>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div>
                                        <strong>No matching resolved accountability cases.</strong>
                                        <p>Try another borrower, reference, case type, or outcome.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="accountability-table-browser-footer"
                id="resolved-history-pager"
            >
                <label class="accountability-page-size-control">
                    <span>Show</span>

                    <select id="resolved-history-page-size">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>

                    <span>per page</span>
                </label>

                <nav
                    class="accountability-pagination"
                    id="resolved-history-pages"
                    aria-label="Resolved accountability pages"
                ></nav>
            </div>

            <script>
            (() => {
                const section = document.getElementById('resolved-history');
                if (!section) return;

                const rows = [...section.querySelectorAll('[data-resolved-history-row]')];
                if (rows.length === 0) return;

                const search = section.querySelector('#resolved-history-search');
                const typeSelect = section.querySelector('#resolved-history-case-type');
                const outcomeSelect = section.querySelector('#resolved-history-outcome');
                const count = section.querySelector('#resolved-history-count');
                const empty = section.querySelector('#resolved-history-none');
                const showMore = section.querySelector('#resolved-history-show-more');

                const initialLimit = {{ $resolvedHistoryInitialLimit }};
                let expanded = false;

                function renderResolvedHistory() {
                    const query =
                        search?.value.trim().toLocaleLowerCase() || '';

                    const type =
                        typeSelect?.value || 'all';

                    const outcome =
                        outcomeSelect?.value || 'all';

                    const filtering =
                        query !== '' ||
                        type !== 'all' ||
                        outcome !== 'all';

                    let matched = 0;
                    let shown = 0;

                    rows.forEach(row => {
                        const matchesSearch =
                            !query ||
                            (row.dataset.search || '')
                                .toLocaleLowerCase()
                                .includes(query);

                        const matchesType =
                            type === 'all' ||
                            row.dataset.caseType === type;

                        const matchesOutcome =
                            outcome === 'all' ||
                            row.dataset.outcome === outcome;

                        const matches =
                            matchesSearch &&
                            matchesType &&
                            matchesOutcome;

                        if (matches) {
                            matched++;
                        }

                        const visible =
                            matches &&
                            (
                                filtering ||
                                expanded ||
                                shown < initialLimit
                            );

                        row.hidden = !visible;

                        if (visible) {
                            shown++;
                        }

                        const detail = row.nextElementSibling;

                        if (
                            detail?.classList.contains(
                                'accountability-case-detail-row'
                            )
                        ) {
                            detail.hidden =
                                !visible ||
                                detail.dataset.expanded !== 'true';
                        }
                    });

                    if (count) {
                        count.textContent = String(matched);
                    }

                    if (empty) {
                        empty.hidden = matched > 0;
                    }

                    if (showMore) {
                        const remaining =
                            Math.max(0, matched - initialLimit);

                        showMore.hidden =
                            filtering ||
                            expanded ||
                            remaining === 0;

                        showMore.textContent =
                            `Show ${remaining} More`;
                    }
                }

                search?.addEventListener(
                    'input',
                    renderResolvedHistory
                );

                typeSelect?.addEventListener(
                    'change',
                    renderResolvedHistory
                );

                outcomeSelect?.addEventListener(
                    'change',
                    renderResolvedHistory
                );

                showMore?.addEventListener('click', () => {
                    expanded = true;
                    renderResolvedHistory();
                });

                renderResolvedHistory();
            })();
            </script>
        @endif
    </article>
@endif
</section>

<style>

.resolved-history-detail-toggle,
.accountability-history-embedded [data-case-toggle] {
    transition: background-color .16s ease, color .16s ease, border-color .16s ease, box-shadow .16s ease, transform .16s ease;
}
.resolved-history-detail-toggle:hover,
.resolved-history-detail-toggle:focus-visible,
.accountability-history-embedded [data-case-toggle]:hover,
.accountability-history-embedded [data-case-toggle]:focus-visible {
    color: #fff;
    background: var(--interactive);
    border-color: var(--interactive);
    box-shadow: var(--shadow-sm);
    transform: translateY(-1px);
}
.accountability-history-embedded { padding: 0; overflow: hidden; }
.accountability-history-embedded .table-wrap { border: 0; border-radius: 0; box-shadow: none; }
.accountability-history-embedded table { margin: 0; }
.resolved-history-card .accountability-case-facts { margin-top: 12px; }

{{-- /* Compact facts inside expanded history rows. This partial is also used in
   the per-borrower staff workspace, where oversight-styles is not loaded. */ --}}
.resolved-history-facts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 1px;
    margin: 0 0 10px;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--border);
}
.resolved-history-facts > div { min-width: 0; padding: 8px 10px; background: var(--surface); }
.resolved-history-facts dt { margin: 0 0 2px; color: var(--text-muted); font-size: 9.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
.resolved-history-facts dd { margin: 0; color: var(--heading); font-size: 12px; font-weight: 700; line-height: 1.35; overflow-wrap: anywhere; }

.accountability-history-embedded .accountability-case-detail-row > td {
    padding: 10px 16px 12px;
    background: var(--surface-subtle);
}

{{-- /* Always-visible compact summary; everything else lives behind "View Details". */ --}}
.resolved-history-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
    margin-top: 8px;
    color: var(--text-secondary);
    font-size: 11.5px;
    font-weight: 650;
}

.resolved-history-card > .accountability-case-details { margin-top: 12px; }

.resolved-history-detail {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 10px 16px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--row-border);
}

.resolved-history-detail h4 {
    margin: 0 0 6px;
    color: var(--text-muted);
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.resolved-history-detail p {
    margin: 0;
    color: var(--heading);
    font-size: 12.5px;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.resolved-history-note { color: var(--text-muted) !important; font-size: 11.5px !important; }

.resolved-history-payment { display: grid; gap: 7px; margin: 0; }
.resolved-history-payment > div { display: grid; gap: 1px; }
.resolved-history-payment dt { color: var(--text-muted); font-size: 11px; }

{{-- /* Receipt numbers can be long, so they wrap instead of widening the page. */ --}}
.resolved-history-payment dd {
    margin: 0;
    color: var(--heading);
    font-size: 12.5px;
    font-weight: 650;
    overflow-wrap: anywhere;
}

.resolved-history-links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--row-border);
}

.resolved-history-links a {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--interactive);
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
}

.resolved-history-links a:hover { text-decoration: underline; }
</style>
