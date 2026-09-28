<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.accountability.partials.official-styles')
</head>
@php($isPreReturn = $isPreReturn ?? false)
<body>
<section class="official-document">
    <table class="document-header" role="presentation">
        <tr>
            <td class="document-logo-cell"><img class="document-logo" src="{{ $logoDataUri }}" alt="CSPC logo"></td>
            <td class="document-header-copy">
                <strong>CAMARINES SUR POLYTECHNIC COLLEGES</strong>
                <span>Supply and Property Management Unit · Nabua, Camarines Sur</span>
            </td>
        </tr>
    </table>

    <div class="document-title">
        <h1>Late Return Notice</h1>
        <div class="document-meta">
            <strong>Reference</strong> {{ $reference }}
            <span class="meta-separator">•</span>
            <strong>Date Issued</strong> {{ $headDate ?: $generatedAt }}
            @if($isPreReturn)
                <span class="meta-separator">•</span>
                <strong>Status</strong> Preliminary
            @endif
        </div>
    </div>

    <div class="section-title">Borrower and Transaction Information</div>
    <table class="info-grid" role="presentation">
        <tr>
            <td><span class="field-label">Borrower</span><span class="field-value">{{ $borrowerName ?: '—' }}</span></td>
            <td><span class="field-label">Office / College / Unit</span><span class="field-value">{{ $officeUnit ?: '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Request No.</span><span class="field-value">{{ $requestNo ?: '—' }}</span></td>
            <td><span class="field-label">Custody No.</span><span class="field-value">{{ $custodyNo ?: '—' }}</span></td>
        </tr>
    </table>

    <div class="section-title">{{ $isPreReturn ? 'Return Information' : 'Final Late-Return Assessment' }}</div>
    <table class="info-grid" role="presentation">
        <tr>
            <td><span class="field-label">Expected Return Date</span><span class="field-value">{{ $expectedReturnDate }}</span></td>
            @unless($isPreReturn)
            <td><span class="field-label">Actual Return Date</span><span class="field-value">{{ $actualReturnDate }}</span></td>
            @endunless
        </tr>
        @unless($isPreReturn)
        <tr>
            <td><span class="field-label">Final Late Days</span><span class="field-value emphasis">{{ $lateDays }}</span></td>
            <td><span class="field-label">Final Disposition</span><span class="field-value emphasis">{{ $disposition }}</span></td>
        </tr>
        @endunless
        <tr>
            <td><span class="field-label">Official Late-Return Fee Rate</span><span class="field-value">{{ $rate !== null ? 'PHP '.number_format($rate, 2).' per day' : 'Not applicable' }}</span></td>
            @unless($isPreReturn)
            <td><span class="field-label">Billing</span><span class="field-value">{{ $amount > 0 ? 'Separate Late Return Billing Statement' : 'No billing required' }}</span></td>
            @endunless
        </tr>
    </table>

    <div class="section-title">Affected Property</div>
    <table class="document-table">
        <thead>
        <tr>
            <th>Description</th>
            <th style="width:18%" class="numeric">Quantity</th>
            <th style="width:20%">Unit</th>
        </tr>
        </thead>
        <tbody>
        @forelse($items as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td class="numeric">{{ (float) $item['quantity'] == (int) $item['quantity'] ? (int) $item['quantity'] : number_format((float) $item['quantity'], 2) }}</td>
                <td>{{ $item['unit'] ?: '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">No item detail is available for this historical transaction.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if($isPreReturn)
        <div class="section-title">Notice</div>
        <p class="decision-copy">The listed property is overdue. Please arrange its return with SPMU. Any applicable charge will be assessed upon physical return and issued through a separate Late Return Billing Statement.</p>
    @else
        @if(trim((string) $decisionBasis) !== '')
            <div class="section-title">Assessment Basis</div>
            <p class="decision-copy">{{ $decisionBasis }}</p>
        @endif
    @endif

    @unless($isPreReturn)
    <table class="signature-table" role="presentation">
        <tr>
            <td colspan="2" style="width: 100%; padding-left: 25%; padding-right: 25%;">
                <div class="signature-label">Confirmed By — SPMU Head</div>
                <div class="signature-space">{!! $headSignatureHtml ?: '' !!}</div>
                <div class="signature-name">{{ $headName }}</div>
                <div class="signature-role">{{ $headDesignation }}</div>
                <div class="signature-date">{{ $headDate }}</div>
            </td>
        </tr>
    </table>
    @endunless

    <div class="document-footer">
        <table role="presentation"><tr>
            <td>SPMU late-return accountability record.</td>
            <td>{{ $reference }}{{ $custodyNo ? ' · '.$custodyNo : '' }}</td>
        </tr></table>
    </div>
</section>
</body>
</html>
