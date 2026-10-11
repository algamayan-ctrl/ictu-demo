{{-- Empty and unavailable-period states of a report preview. --}}
                    <section class="report-no-results {{ $reportPeriodUnavailable ? 'is-period-unavailable' : '' }}" aria-labelledby="report-no-results-title">

    @php
        $emptyReportContext = strtolower(
            ($selectedReportMeta['label'] ?? '') . ' ' . ($selectedReport ?? '')
        );

        $emptyReportIcon =
            str_contains($emptyReportContext, 'inventory') ||
            str_contains($emptyReportContext, 'asset') ||
            str_contains($emptyReportContext, 'utilization')
                ? 'inventory'
                : (
                    str_contains($emptyReportContext, 'return') ||
                    str_contains($emptyReportContext, 'overdue')
                        ? 'return'
                        : (
                            str_contains($emptyReportContext, 'borrower') ||
                            str_contains($emptyReportContext, 'accountability')
                                ? 'borrower'
                                : (
                                    str_contains($emptyReportContext, 'audit') ||
                                    str_contains($emptyReportContext, 'history')
                                        ? 'history'
                                        : 'request'
                                )
                        )
                );
    @endphp

    <div class="report-no-results-inner">
        @if($reportPeriodUnavailable)
            <span class="report-period-unavailable-icon" aria-hidden="true">
                <svg viewBox="0 0 72 72">
                    <rect
                        x="14"
                        y="17"
                        width="44"
                        height="40"
                        rx="5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    />

                    <path
                        d="M14 29h44"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    />

                    <path
                        d="M24 12v10M48 12v10"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        stroke-linecap="round"
                    />

                    <path
                        d="M25 38h5M35 38h5M45 38h5M25 47h5M35 47h5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                    />

                    <circle
                        cx="51"
                        cy="51"
                        r="9"
                        fill="var(--surface-elevated)"
                        stroke="currentColor"
                        stroke-width="1.7"
                    />

                    <path
                        d="M47.5 51h7"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        stroke-linecap="round"
                    />
                </svg>
            </span>
        @endif

        <span class="report-no-results-illustration" aria-hidden="true">

            @if($emptyReportIcon === 'inventory')
                <svg viewBox="0 0 72 72">
                    <path class="empty-soft" d="M14 25 36 13l22 12v27L36 64 14 52z"/>
                    <path class="empty-line" d="M13 24 36 12l23 12v27L36 63 13 51z"/>
                    <path class="empty-line" d="M13 24 36 37l23-13M36 37v26"/>
                    <path class="empty-line" d="M25 18 48 31"/>
                    <circle class="empty-line" cx="54" cy="52" r="8"/>
                    <path class="empty-line" d="m60 58 6 6"/>
                </svg>

            @elseif($emptyReportIcon === 'return')
                <svg viewBox="0 0 72 72">
                    <path class="empty-soft" d="M20 11h31l8 9v39H20z"/>
                    <path class="empty-line" d="M19 10h30l9 9v40H19z"/>
                    <path class="empty-line" d="M49 10v10h9M27 30h22M27 38h17"/>
                    <path class="empty-line" d="M11 52h25M11 52l7-7M11 52l7 7"/>
                </svg>

            @elseif($emptyReportIcon === 'borrower')
                <svg viewBox="0 0 72 72">
                    <circle class="empty-soft" cx="33" cy="28" r="14"/>
                    <circle class="empty-line" cx="33" cy="25" r="10"/>
                    <path class="empty-line" d="M15 60c2-12 9-18 18-18s16 6 18 18"/>
                    <circle class="empty-line" cx="55" cy="50" r="8"/>
                    <path class="empty-line" d="m61 56 6 6"/>
                </svg>

            @elseif($emptyReportIcon === 'history')
                <svg viewBox="0 0 72 72">
                    <path class="empty-soft" d="M19 12h31l9 9v39H19z"/>
                    <path class="empty-line" d="M18 10h31l9 10v40H18zM49 10v11h9"/>
                    <path class="empty-line" d="M26 31h22M26 39h18M26 47h13"/>
                    <circle class="empty-line" cx="55" cy="53" r="8"/>
                    <path class="empty-line" d="M55 49v5l4 2"/>
                </svg>

            @else
                <svg viewBox="0 0 72 72">
                    <path class="empty-soft" d="M19 11h31l9 9v39H19z"/>
                    <path class="empty-line" d="M18 10h31l9 10v40H18zM49 10v11h9"/>
                    <path class="empty-line" d="M27 31h22M27 39h19M27 47h12"/>
                    <circle class="empty-line" cx="53" cy="51" r="8"/>
                    <path class="empty-line" d="m59 57 6 6"/>
                    <path class="empty-spark" d="m13 20 1.4 3.2 3.2 1.4-3.2 1.4-1.4 3.2-1.4-3.2-3.2-1.4 3.2-1.4z"/>
                </svg>
            @endif

        </span>

        <div>
            <p class="report-preview-label">No matching records</p>

            @if($reportPeriodUnavailable)
                <h3 id="report-no-results-title">{{ $reportPeriodUnavailableTitle }}</h3>
                <p>{{ $reportPeriodUnavailableMessage }}</p>
            @else
                <h3 id="report-no-results-title">No records yet.</h3>
                <p>{{ App\Reports\ReportCatalogue::emptyMessage($selectedReport) }}</p>
            @endif

            <div class="report-no-results-actions">
                <a class="button secondary ui-pressable" href="#report-builder" data-focus-report-builder>Change Period or Filters</a>
            </div>
        </div>
    </div>

</section>
